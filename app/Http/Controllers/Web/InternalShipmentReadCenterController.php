<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CarrierService;
use App\Services\InternalKycOperationalEffectService;
use App\Services\ShipmentTimelineService;
use App\Support\CanonicalShipmentStatus;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use App\Support\PortalShipmentLabeler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InternalShipmentReadCenterController extends Controller
{
    public function __construct(
        private readonly ShipmentTimelineService $shipmentTimelineService,
        private readonly CarrierService $carrierService,
        private readonly InternalKycOperationalEffectService $operationalEffectService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), $this->allowedStatuses()),
            'carrier' => strtolower(trim((string) $request->query('carrier', ''))),
            'source' => $this->normalizedFilter((string) $request->query('source', ''), $this->sourceOptions()),
            'international' => $this->normalizedBooleanFilter((string) $request->query('international', '')),
            'cod' => $this->normalizedBooleanFilter((string) $request->query('cod', '')),
        ];

        $baseQuery = $this->shipmentIndexQuery($filters);
        $stats = $this->stats(clone $baseQuery);

        $shipments = $baseQuery
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $shipments->setCollection(
            $shipments->getCollection()->map(
                fn (Shipment $shipment): array => $this->buildIndexRow($shipment)
            )
        );

        return view('pages.admin.shipments-index', [
            'shipments' => $shipments,
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'sourceOptions' => $this->sourceLabels(),
            'carrierOptions' => $this->carrierOptions(),
        ]);
    }

    public function show(Request $request, string $shipment, InternalControlPlane $controlPlane): View
    {
        $shipmentModel = Shipment::query()
            ->withoutGlobalScopes()
            ->with($this->shipmentDetailRelations())
            ->findOrFail($shipment);

        $timeline = $this->buildTimeline($shipmentModel);
        $documents = $this->buildDocumentSummaries($shipmentModel);
        $publicTracking = $this->publicTrackingSummary($shipmentModel);
        $kycSummary = $this->buildKycRestrictionSummary($shipmentModel->account);
        $user = $request->user();
        $notifications = $this->buildNotificationsPanel($shipmentModel, $user);

        return view('pages.admin.shipments-show', [
            'shipment' => $shipmentModel,
            'shipmentSummary' => $this->shipmentSummary($shipmentModel),
            'accountSummary' => $this->accountSummary($shipmentModel),
            'carrierSummary' => $this->carrierSummary($shipmentModel),
            'trackingSummary' => $this->trackingSummary($shipmentModel),
            'timeline' => $timeline,
            'documents' => $documents,
            'documentHeadline' => $this->documentHeadline($documents),
            'publicTracking' => $publicTracking,
            'notifications' => $notifications,
            'kycSummary' => $kycSummary,
            'canViewAccount' => $this->canViewAccount($user, $controlPlane),
            'canViewKyc' => $this->canViewKyc($user, $controlPlane),
            'canViewDocuments' => $this->canViewDocuments($user, $controlPlane),
            'canCreateTickets' => $this->canCreateTickets($user, $controlPlane),
        ]);
    }

    /**
     * @param array{q: string, status: string, carrier: string, source: string, international: string, cod: string} $filters
     */
    private function shipmentIndexQuery(array $filters): Builder
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->with($this->shipmentIndexRelations())
            ->withCount([
                'events as timeline_events_count' => static function (Builder $query): void {
                    $query->withoutGlobalScopes();
                },
                'carrierDocuments as available_documents_count' => static function (Builder $query): void {
                    $query->withoutGlobalScopes()->where('is_available', true);
                },
            ])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('reference_number', 'like', $search)
                        ->orWhere('tracking_number', 'like', $search)
                        ->orWhere('carrier_tracking_number', 'like', $search)
                        ->orWhere('carrier_shipment_id', 'like', $search)
                        ->orWhere('recipient_name', 'like', $search)
                        ->orWhere('recipient_phone', 'like', $search)
                        ->orWhereHas('carrierShipment', function (Builder $carrierQuery) use ($search): void {
                            $carrierQuery->where('tracking_number', 'like', $search)
                                ->orWhere('awb_number', 'like', $search)
                                ->orWhere('carrier_shipment_id', 'like', $search);
                        })
                        ->orWhereHas('account', function (Builder $accountQuery) use ($search): void {
                            $accountQuery->withoutGlobalScopes()
                                ->where(function (Builder $accountInner) use ($search): void {
                                    $accountInner->where('name', 'like', $search)
                                        ->orWhere('slug', 'like', $search)
                                        ->orWhereHas('organizationProfile', function (Builder $organizationQuery) use ($search): void {
                                            $organizationQuery->where('legal_name', 'like', $search)
                                                ->orWhere('trade_name', 'like', $search);
                                        });
                                });
                        });
                });
            })
            ->when($filters['status'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['carrier'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->whereRaw('LOWER(COALESCE(carrier_code, "")) = ?', [$filters['carrier']])
                        ->orWhereHas('carrierShipment', function (Builder $carrierQuery) use ($filters): void {
                            $carrierQuery->whereRaw('LOWER(COALESCE(carrier_code, "")) = ?', [$filters['carrier']]);
                        });
                });
            })
            ->when($filters['source'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('source', $filters['source']);
            })
            ->when($filters['international'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('is_international', $filters['international'] === 'yes');
            })
            ->when($filters['cod'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('is_cod', $filters['cod'] === 'yes');
            });
    }

    /**
     * @return array<int, string>
     */
    private function shipmentIndexRelations(): array
    {
        return [
            'account.organizationProfile',
            'account.kycVerification',
            'user',
            'carrierShipment',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function shipmentDetailRelations(): array
    {
        return [
            'account.organizationProfile',
            'account.kycVerification',
            'user',
            'store',
            'order',
            'carrierShipment',
            'parcels',
        ];
    }

    /**
     * @return array{total: int, in_flight: int, requires_attention: int, kyc_blocked: int}
     */
    private function stats(Builder $baseQuery): array
    {
        return [
            'total' => (clone $baseQuery)->count(),
            'in_flight' => (clone $baseQuery)->whereIn('status', [
                Shipment::STATUS_PURCHASED,
                Shipment::STATUS_READY_FOR_PICKUP,
                Shipment::STATUS_PICKED_UP,
                Shipment::STATUS_IN_TRANSIT,
                Shipment::STATUS_OUT_FOR_DELIVERY,
            ])->count(),
            'requires_attention' => (clone $baseQuery)->whereIn('status', [
                Shipment::STATUS_REQUIRES_ACTION,
                Shipment::STATUS_EXCEPTION,
                Shipment::STATUS_FAILED,
            ])->count(),
            'kyc_blocked' => (clone $baseQuery)->where('status', Shipment::STATUS_KYC_BLOCKED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIndexRow(Shipment $shipment): array
    {
        return [
            'shipment' => $shipment,
            'shipmentSummary' => $this->shipmentSummary($shipment),
            'accountSummary' => $this->accountSummary($shipment),
            'carrierSummary' => $this->carrierSummary($shipment),
            'trackingSummary' => $this->trackingSummary($shipment),
            'timelinePreview' => $this->timelinePreview($shipment),
            'documentsSummary' => $this->documentsCountSummary($shipment),
            'publicTracking' => $this->publicTrackingSummary($shipment),
            'kycSummary' => $this->buildKycRestrictionSummary($shipment->account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentSummary(Shipment $shipment): array
    {
        $normalizedStatus = CanonicalShipmentStatus::fromShipment($shipment);

        return [
            'reference' => (string) ($shipment->reference_number ?: $shipment->id),
            'workflow_status' => (string) ($shipment->status ?? ''),
            'workflow_status_label' => $this->workflowStatusLabel((string) ($shipment->status ?? '')),
            'normalized_status' => $normalizedStatus,
            'normalized_status_label' => PortalShipmentLabeler::status(
                $normalizedStatus,
                CanonicalShipmentStatus::label($normalizedStatus)
            ),
            'source' => (string) ($shipment->source ?? ''),
            'source_label' => $this->sourceLabels()[(string) ($shipment->source ?? '')] ?? $this->headline((string) ($shipment->source ?? '')),
            'created_at' => optional($shipment->created_at)->format('Y-m-d H:i'),
            'updated_at' => optional($shipment->updated_at)->format('Y-m-d H:i'),
            'recipient_city' => (string) ($shipment->recipient_city ?? ''),
            'recipient_country' => (string) ($shipment->recipient_country ?? ''),
            'flags' => array_values(array_filter([
                $shipment->is_international ? 'International' : null,
                $shipment->is_cod ? 'COD' : null,
                $shipment->has_dangerous_goods ? 'DG' : null,
                $shipment->status === Shipment::STATUS_KYC_BLOCKED ? 'KYC blocked' : null,
                $shipment->status === Shipment::STATUS_REQUIRES_ACTION ? 'Requires action' : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSummary(Shipment $shipment): array
    {
        $account = $shipment->account;

        if (! $account instanceof Account) {
            return [
                'name' => 'Unknown account',
                'slug' => '—',
                'type_label' => 'Unknown',
                'owner_label' => 'No linked account',
            ];
        }

        $organizationName = trim((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: ''));
        $ownerName = trim((string) ($shipment->user?->name ?: ''));
        $ownerEmail = trim((string) ($shipment->user?->email ?: ''));

        return [
            'account' => $account,
            'name' => (string) $account->name,
            'slug' => (string) ($account->slug ?? '—'),
            'type_label' => $account->isOrganization() ? 'Organization' : 'Individual',
            'owner_label' => $account->isOrganization()
                ? ($organizationName !== '' ? $organizationName : 'Organization profile incomplete')
                : ($ownerName !== '' ? $ownerName : 'Owner not visible'),
            'owner_secondary' => $account->isOrganization()
                ? ($ownerName !== '' ? $ownerName . ($ownerEmail !== '' ? ' • ' . $ownerEmail : '') : 'Owner not visible')
                : ($ownerEmail !== '' ? $ownerEmail : 'No visible email'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function carrierSummary(Shipment $shipment): array
    {
        $carrierCode = (string) ($shipment->carrierShipment?->carrier_code ?? $shipment->carrier_code ?? '');
        $carrierName = (string) ($shipment->carrierShipment?->carrier_name ?? $shipment->carrier_name ?? '');
        $serviceCode = (string) ($shipment->carrierShipment?->service_code ?? $shipment->service_code ?? '');
        $serviceName = (string) ($shipment->carrierShipment?->service_name ?? $shipment->service_name ?? '');

        return [
            'carrier_label' => PortalShipmentLabeler::carrier($carrierCode, $carrierName),
            'service_label' => PortalShipmentLabeler::service($serviceCode, $serviceName),
            'pair_label' => PortalShipmentLabeler::carrierServicePair($carrierCode, $serviceCode, $carrierName, $serviceName),
            'carrier_shipment_id' => (string) ($shipment->carrierShipment?->carrier_shipment_id ?? $shipment->carrier_shipment_id ?? '—'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function trackingSummary(Shipment $shipment): array
    {
        $trackingNumber = (string) ($shipment->carrierShipment?->tracking_number ?: $shipment->tracking_number ?: $shipment->carrier_tracking_number ?: '—');
        $awbNumber = (string) ($shipment->carrierShipment?->awb_number ?: '—');

        return [
            'tracking_number' => $trackingNumber,
            'awb_number' => $awbNumber,
            'tracking_reference' => $trackingNumber !== '—' ? $trackingNumber : ((string) ($shipment->reference_number ?: $shipment->id)),
            'tracking_url' => trim((string) ($shipment->tracking_url ?? '')),
        ];
    }

    /**
     * @return array{label: string, detail: string, last_updated: string, events_count: int}
     */
    private function timelinePreview(Shipment $shipment): array
    {
        $normalizedStatus = CanonicalShipmentStatus::fromShipment($shipment);
        $lastUpdated = optional($shipment->tracking_updated_at ?? $shipment->updated_at)->format('Y-m-d H:i') ?? '—';
        $eventsCount = (int) ($shipment->timeline_events_count ?? 0);

        return [
            'label' => PortalShipmentLabeler::status($normalizedStatus, CanonicalShipmentStatus::label($normalizedStatus)),
            'detail' => $eventsCount > 0
                ? 'Timeline events: ' . number_format($eventsCount)
                : 'No recorded timeline events yet',
            'last_updated' => $lastUpdated,
            'events_count' => $eventsCount,
        ];
    }

    /**
     * @return array{count: int, label: string}
     */
    private function documentsCountSummary(Shipment $shipment): array
    {
        $count = (int) ($shipment->available_documents_count ?? 0);

        return [
            'count' => $count,
            'label' => $count > 0 ? number_format($count) . ' available document(s)' : 'No available documents',
        ];
    }

    /**
     * @return array{
     *   available: bool,
     *   label: string,
     *   detail: string,
     *   enabled_at: string,
     *   expires_at: string,
     *   url: string|null
     * }
     */
    private function publicTrackingSummary(Shipment $shipment): array
    {
        $enabledAt = optional($shipment->public_tracking_enabled_at)->format('Y-m-d H:i') ?? '—';
        $expiresAt = optional($shipment->public_tracking_expires_at)->format('Y-m-d H:i') ?? '—';
        $hasToken = trim((string) ($shipment->getRawOriginal('public_tracking_token_hash') ?? '')) !== '';
        $enabled = $hasToken
            && $shipment->public_tracking_enabled_at !== null
            && ($shipment->public_tracking_expires_at === null || $shipment->public_tracking_expires_at->isFuture());

        return [
            'available' => $enabled,
            'label' => $enabled ? 'Public tracking is enabled' : 'Public tracking is not enabled',
            'detail' => $enabled
                ? 'An existing public tracking link is already active for this shipment.'
                : 'No active public tracking link is exposed from this internal surface.',
            'enabled_at' => $enabledAt,
            'expires_at' => $expiresAt,
            'url' => $enabled ? $this->existingPublicTrackingUrl($shipment) : null,
        ];
    }

    /**
     * @return array{
     *   visible: bool,
     *   total_count: int,
     *   delivered_count: int,
     *   issue_count: int,
     *   latest_created_at: string,
     *   channels: array<int, string>,
     *   items: Collection<int, array{
     *     subject: string,
     *     event_type_label: string,
     *     channel_label: string,
     *     status_label: string,
     *     created_at_display: string,
     *     sent_at_display: string
     *   }>
     * }
     */
    private function buildNotificationsPanel(Shipment $shipment, ?User $user): array
    {
        if (! $this->canViewNotifications($user) || ! Schema::hasTable('notifications')) {
            return [
                'visible' => false,
                'total_count' => 0,
                'delivered_count' => 0,
                'issue_count' => 0,
                'latest_created_at' => '-',
                'channels' => [],
                'items' => collect(),
            ];
        }

        $baseQuery = Notification::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $shipment->account_id)
            ->where('entity_type', 'shipment')
            ->where('entity_id', (string) $shipment->id);

        $totalCount = (clone $baseQuery)->count();
        $deliveredCount = (clone $baseQuery)
            ->whereIn('status', [Notification::STATUS_SENT, Notification::STATUS_DELIVERED])
            ->count();
        $issueCount = (clone $baseQuery)
            ->whereIn('status', [
                Notification::STATUS_FAILED,
                Notification::STATUS_BOUNCED,
                Notification::STATUS_RETRYING,
                Notification::STATUS_DLQ,
            ])
            ->count();
        $latestCreatedAt = $this->displayDateTime((clone $baseQuery)->latest('created_at')->value('created_at')) ?? '-';

        $channels = (clone $baseQuery)
            ->select('channel')
            ->distinct()
            ->pluck('channel')
            ->filter(static fn ($channel): bool => is_string($channel) && trim($channel) !== '')
            ->map(fn (string $channel): string => $this->notificationChannelLabel($channel))
            ->values()
            ->all();

        $items = $baseQuery
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(function (Notification $notification): array {
                $subject = trim((string) (
                    $notification->subject
                    ?? data_get($notification->event_data, 'title')
                    ?? $notification->event_type
                    ?? 'Shipment notification'
                ));

                return [
                    'subject' => $subject !== '' ? $subject : 'Shipment notification',
                    'event_type_label' => PortalShipmentLabeler::event(
                        (string) $notification->event_type,
                        $subject
                    ),
                    'channel_label' => $this->notificationChannelLabel((string) $notification->channel),
                    'status_label' => $this->notificationStatusLabel((string) $notification->status),
                    'created_at_display' => optional($notification->created_at)->format('Y-m-d H:i') ?? '-',
                    'sent_at_display' => optional($notification->sent_at)->format('Y-m-d H:i') ?? '-',
                ];
            })
            ->values();

        return [
            'visible' => true,
            'total_count' => $totalCount,
            'delivered_count' => $deliveredCount,
            'issue_count' => $issueCount,
            'latest_created_at' => $latestCreatedAt,
            'channels' => $channels,
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   label: string,
     *   queue_summary: string,
     *   action_label: string,
     *   blocked_shipments_count: int,
     *   restriction_names: array<int, string>
     * }|null
     */
    private function buildKycRestrictionSummary(?Account $account): ?array
    {
        if (! $account instanceof Account) {
            return null;
        }

        $verification = $account->kycVerification;
        $status = trim((string) (
            $verification?->status
            ?? AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''))
        ));
        $display = $verification?->statusDisplay() ?? ['label' => $this->headline($status)];
        $blockedShipmentsCount = Shipment::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('status', Shipment::STATUS_KYC_BLOCKED)
            ->count();
        $capabilities = $verification?->capabilities() ?? [];
        $effect = $this->operationalEffectService->summarize($account, $status, $capabilities, $blockedShipmentsCount);
        $restrictionNames = [];

        if (Schema::hasTable('verification_restrictions')) {
            $restrictionNames = \App\Models\VerificationRestriction::getForStatus($status)
                ->pluck('name')
                ->filter(static fn ($name): bool => is_string($name) && trim($name) !== '')
                ->map(static fn (string $name): string => trim($name))
                ->values()
                ->all();
        }

        return [
            'status' => $status,
            'label' => (string) ($display['label'] ?? $this->headline($status)),
            'queue_summary' => (string) $effect['queue_summary'],
            'action_label' => (string) $effect['action_label'],
            'blocked_shipments_count' => (int) $effect['blocked_shipments_count'],
            'restriction_names' => $restrictionNames,
        ];
    }

    /**
     * @return array{
     *   current_status_label: string,
     *   last_updated: string|null,
     *   total_events: int,
     *   events: Collection<int, array<string, mixed>>
     * }
     */
    private function buildTimeline(Shipment $shipment): array
    {
        $timeline = $this->shipmentTimelineService->present($shipment);

        return [
            'current_status_label' => PortalShipmentLabeler::status(
                (string) ($timeline['current_status'] ?? ''),
                (string) ($timeline['current_status_label'] ?? '')
            ),
            'last_updated' => $this->displayDateTime($timeline['last_updated'] ?? null),
            'total_events' => (int) ($timeline['total_events'] ?? 0),
            'events' => collect($timeline['events'] ?? [])
                ->map(function (array $event): array {
                    return array_merge($event, [
                        'event_time_display' => $this->displayDateTime($event['event_time'] ?? null),
                        'event_type_label' => PortalShipmentLabeler::event(
                            (string) ($event['event_type'] ?? ''),
                            (string) ($event['event_type_label'] ?? '')
                        ),
                        'status_label' => PortalShipmentLabeler::status(
                            (string) ($event['status'] ?? ''),
                            (string) ($event['status_label'] ?? '')
                        ),
                        'source_label' => PortalShipmentLabeler::source(
                            (string) ($event['source'] ?? ''),
                            (string) ($event['source_label'] ?? '')
                        ),
                    ]);
                })
                ->values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildDocumentSummaries(Shipment $shipment): Collection
    {
        return collect($this->carrierService->listDocuments($shipment))
            ->map(function (array $document): array {
                return array_merge($document, [
                    'document_type_label' => PortalShipmentLabeler::documentType(
                        (string) ($document['document_type'] ?? $document['type'] ?? ''),
                        (string) ($document['document_type'] ?? $document['type'] ?? '')
                    ),
                    'carrier_label' => PortalShipmentLabeler::carrier((string) ($document['carrier_code'] ?? '')),
                    'format_label' => PortalShipmentLabeler::documentFormat(
                        (string) ($document['file_format'] ?? $document['format'] ?? ''),
                        strtoupper((string) ($document['file_format'] ?? $document['format'] ?? ''))
                    ),
                    'retrieval_mode_label' => PortalShipmentLabeler::retrievalMode(
                        (string) ($document['retrieval_mode'] ?? ''),
                        (string) ($document['retrieval_mode'] ?? '')
                    ),
                    'created_at_display' => $this->displayDateTime($document['created_at'] ?? null),
                    'size_label' => $this->humanFileSize($document['size'] ?? null),
                ]);
            })
            ->values();
    }

    private function documentHeadline(Collection $documents): string
    {
        return $documents->isEmpty()
            ? 'No carrier artifacts are currently available on this shipment.'
            : number_format($documents->count()) . ' safe carrier document summary item(s)';
    }

    private function canViewAccount(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('accounts.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function canViewKyc(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('kyc.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_DETAIL);
    }

    private function canViewDocuments(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('shipments.documents.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS);
    }

    private function canCreateTickets(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('tickets.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_CREATE);
    }

    private function canViewNotifications(?User $user): bool
    {
        return $user instanceof User
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('notifications.read');
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        return array_keys($this->statusOptions());
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            Shipment::STATUS_KYC_BLOCKED => 'KYC blocked',
            Shipment::STATUS_REQUIRES_ACTION => 'Requires action',
            Shipment::STATUS_PURCHASED => 'Purchased',
            Shipment::STATUS_IN_TRANSIT => 'In transit',
            Shipment::STATUS_OUT_FOR_DELIVERY => 'Out for delivery',
            Shipment::STATUS_DELIVERED => 'Delivered',
            Shipment::STATUS_EXCEPTION => 'Exception',
            Shipment::STATUS_CANCELLED => 'Cancelled',
            Shipment::STATUS_RETURNED => 'Returned',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sourceOptions(): array
    {
        return [
            Shipment::SOURCE_DIRECT,
            Shipment::SOURCE_ORDER,
            Shipment::SOURCE_BULK,
            Shipment::SOURCE_RETURN,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sourceLabels(): array
    {
        return [
            Shipment::SOURCE_DIRECT => 'Direct',
            Shipment::SOURCE_ORDER => 'Order',
            Shipment::SOURCE_BULK => 'Bulk',
            Shipment::SOURCE_RETURN => 'Return',
        ];
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function carrierOptions(): Collection
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->select(['carrier_code', 'carrier_name'])
            ->whereNotNull('carrier_code')
            ->where('carrier_code', '!=', '')
            ->distinct()
            ->orderBy('carrier_code')
            ->get()
            ->map(function (Shipment $shipment): array {
                $value = strtolower((string) $shipment->carrier_code);

                return [
                    'value' => $value,
                    'label' => PortalShipmentLabeler::carrier($shipment->carrier_code, $shipment->carrier_name),
                ];
            })
            ->unique('value')
            ->values();
    }

    private function normalizedFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function normalizedBooleanFilter(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    private function workflowStatusLabel(string $status): string
    {
        return $this->statusOptions()[$status] ?? $this->headline($status);
    }

    private function notificationChannelLabel(string $channel): string
    {
        return match (strtolower(trim($channel))) {
            Notification::CHANNEL_IN_APP => 'In app',
            Notification::CHANNEL_EMAIL => 'Email',
            Notification::CHANNEL_SMS => 'SMS',
            Notification::CHANNEL_WEBHOOK => 'Webhook',
            Notification::CHANNEL_SLACK => 'Slack',
            default => $this->headline($channel),
        };
    }

    private function notificationStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            Notification::STATUS_PENDING => 'Pending',
            Notification::STATUS_QUEUED => 'Queued',
            Notification::STATUS_SENDING => 'Sending',
            Notification::STATUS_SENT => 'Sent',
            Notification::STATUS_DELIVERED => 'Delivered',
            Notification::STATUS_FAILED => 'Failed',
            Notification::STATUS_BOUNCED => 'Bounced',
            Notification::STATUS_RETRYING => 'Retrying',
            Notification::STATUS_DLQ => 'Dead letter',
            default => $this->headline($status),
        };
    }

    private function headline(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 'Not available';
        }

        return Str::of($normalized)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title()
            ->value();
    }

    private function humanFileSize(mixed $size): string
    {
        $bytes = filter_var($size, FILTER_VALIDATE_INT);

        if ($bytes === false || $bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex];
    }

    private function displayDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return trim($value);
        }
    }

    private function existingPublicTrackingUrl(Shipment $shipment): ?string
    {
        if (! Schema::hasColumns('shipments', ['public_tracking_token', 'public_tracking_token_hash'])) {
            return null;
        }

        $hasTokenHash = trim((string) ($shipment->getRawOriginal('public_tracking_token_hash') ?? '')) !== '';

        if (! $hasTokenHash) {
            return null;
        }

        try {
            $token = trim((string) ($shipment->public_tracking_token ?? ''));
        } catch (Throwable) {
            return null;
        }

        if ($token === '') {
            return null;
        }

        return route('public.tracking.show', ['token' => $token]);
    }
}
