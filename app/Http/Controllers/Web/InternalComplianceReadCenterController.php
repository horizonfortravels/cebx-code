<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\DgMetadata;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Services\InternalKycOperationalEffectService;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalComplianceReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalKycOperationalEffectService $operationalEffectService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $this->normalizedFilter((string) $request->query('type', ''), ['individual', 'organization']),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), $this->allowedStatuses()),
            'review' => $this->normalizedFilter((string) $request->query('review', ''), ['attention', 'clear', 'open']),
        ];

        $baseQuery = $this->complianceQueueQuery($filters);
        $stats = $this->queueStats(clone $baseQuery);

        $cases = $baseQuery
            ->orderByRaw($this->statusOrderExpression())
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $latestAudit = $this->latestAuditByDeclaration($cases->getCollection());

        $cases->setCollection(
            $cases->getCollection()->map(
                fn (ContentDeclaration $declaration): array => $this->buildQueueRow(
                    $declaration,
                    $latestAudit->get((string) $declaration->id)
                )
            )
        );

        return view('pages.admin.compliance-index', [
            'cases' => $cases,
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'reviewOptions' => $this->reviewOptions(),
        ]);
    }

    public function show(Request $request, string $declaration, InternalControlPlane $controlPlane): View
    {
        $declarationModel = $this->resolveDeclaration($declaration);
        $shipment = $declarationModel->shipment instanceof Shipment ? $declarationModel->shipment : null;
        $account = $shipment?->account instanceof Account ? $shipment->account : null;
        $currentUser = $request->user();
        $auditEntries = $this->auditEntries($declarationModel);
        $canViewKyc = $this->canViewKyc($currentUser, $controlPlane);
        $canViewBilling = $this->canViewBilling($currentUser, $controlPlane);
        $linkedPreflightHold = $canViewBilling ? $this->linkedPreflightHold($shipment, $account) : null;

        return view('pages.admin.compliance-show', [
            'declaration' => $declarationModel,
            'shipment' => $shipment,
            'account' => $account,
            'shipmentSummary' => $shipment ? $this->shipmentSummary($shipment) : null,
            'accountSummary' => $account ? $this->accountSummary($account) : null,
            'declarationSummary' => $this->declarationSummary($declarationModel),
            'reviewSummary' => $this->reviewState($declarationModel),
            'legalSummary' => $this->legalSummary($declarationModel),
            'workflowSummary' => $this->workflowSummary($declarationModel, $shipment),
            'notesSummary' => $this->notesSummary($declarationModel, $shipment, $auditEntries),
            'dgMetadataSummary' => $this->dgMetadataSummary($declarationModel->dgMetadata),
            'restrictionSummary' => $account ? $this->buildRestrictionSummary($account) : null,
            'auditEntries' => $auditEntries,
            'canManageComplianceActions' => $this->canManageComplianceActions($currentUser, $controlPlane),
            'requestCorrectionAction' => $this->requestCorrectionAction($declarationModel),
            'canViewShipment' => $this->canViewShipment($currentUser, $controlPlane),
            'canViewAccount' => $this->canViewAccount($currentUser, $controlPlane),
            'canViewKyc' => $canViewKyc,
            'canViewBilling' => $canViewBilling,
            'hasBillingContext' => $this->hasBillingContext($account),
            'linkedPreflightHold' => $linkedPreflightHold,
        ]);
    }

    /**
     * @param array{q: string, type: string, status: string, review: string} $filters
     */
    private function complianceQueueQuery(array $filters): Builder
    {
        return ContentDeclaration::query()
            ->withoutGlobalScopes()
            ->with($this->declarationRelations())
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->whereHas('shipment', function (Builder $shipmentQuery) use ($search): void {
                    $shipmentQuery->withoutGlobalScopes()
                        ->where(function (Builder $shipmentInner) use ($search): void {
                            $shipmentInner->where('reference_number', 'like', $search)
                                ->orWhere('tracking_number', 'like', $search)
                                ->orWhereHas('account', function (Builder $accountQuery) use ($search): void {
                                    $accountQuery->withoutGlobalScopes()
                                        ->where(function (Builder $accountInner) use ($search): void {
                                            $accountInner->where('name', 'like', $search)
                                                ->orWhere('slug', 'like', $search)
                                                ->orWhereHas('users', function (Builder $userQuery) use ($search): void {
                                                    $userQuery->withoutGlobalScopes()
                                                        ->where(function (Builder $userInner) use ($search): void {
                                                            $userInner->where('name', 'like', $search)
                                                                ->orWhere('email', 'like', $search);
                                                        });

                                                    if (Schema::hasColumn('users', 'user_type')) {
                                                        $userQuery->where('user_type', 'external');
                                                    }
                                                })
                                                ->orWhereHas('organizationProfile', function (Builder $organizationQuery) use ($search): void {
                                                    $organizationQuery->where('legal_name', 'like', $search)
                                                        ->orWhere('trade_name', 'like', $search)
                                                        ->orWhere('registration_number', 'like', $search);
                                                });
                                        });
                                });
                        });
                });
            })
            ->when($filters['type'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('shipment.account', function (Builder $accountQuery) use ($filters): void {
                    $accountQuery->withoutGlobalScopes()->where('type', $filters['type']);
                });
            })
            ->when($filters['status'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['review'] !== '', function (Builder $query) use ($filters): void {
                if ($filters['review'] === 'attention') {
                    $query->whereIn('status', [
                        ContentDeclaration::STATUS_HOLD_DG,
                        ContentDeclaration::STATUS_REQUIRES_ACTION,
                    ]);

                    return;
                }

                if ($filters['review'] === 'clear') {
                    $query->where('status', ContentDeclaration::STATUS_COMPLETED);

                    return;
                }

                $query->whereIn('status', [
                    ContentDeclaration::STATUS_PENDING,
                    ContentDeclaration::STATUS_EXPIRED,
                ]);
            });
    }

    /**
     * @return array<int, string>
     */
    private function declarationRelations(): array
    {
        return [
            'shipment' => function ($query): void {
                $query->withoutGlobalScopes()
                    ->with([
                        'balanceReservation',
                        'account' => function ($accountQuery): void {
                            $accountQuery->withoutGlobalScopes()
                                ->with([
                                    'organizationProfile',
                                    'users' => function ($userQuery): void {
                                        $userQuery->withoutGlobalScopes()
                                            ->orderByDesc('is_owner')
                                            ->orderBy('name');

                                        if (Schema::hasColumn('users', 'user_type')) {
                                            $userQuery->where('user_type', 'external');
                                        }
                                    },
                                ]);
                        },
                    ]);
            },
            'dgMetadata',
            'waiverVersion',
        ];
    }

    /**
     * @return array{total: int, attention: int, waiver_pending: int, dg_flagged: int}
     */
    private function queueStats(Builder $baseQuery): array
    {
        $all = (clone $baseQuery)->get();

        return [
            'total' => $all->count(),
            'attention' => $all->filter(fn (ContentDeclaration $declaration): bool => $this->reviewBucket($declaration) === 'attention')->count(),
            'waiver_pending' => $all->filter(fn (ContentDeclaration $declaration): bool => $this->waiverPending($declaration))->count(),
            'dg_flagged' => $all->filter(fn (ContentDeclaration $declaration): bool => (bool) $declaration->contains_dangerous_goods)->count(),
        ];
    }

    /**
     * @param Collection<int, ContentDeclaration> $declarations
     * @return Collection<string, DgAuditLog>
     */
    private function latestAuditByDeclaration(Collection $declarations): Collection
    {
        $declarationIds = $declarations
            ->map(fn (ContentDeclaration $declaration): string => (string) $declaration->id)
            ->filter()
            ->values();

        if ($declarationIds->isEmpty()) {
            return collect();
        }

        return DgAuditLog::query()
            ->withoutGlobalScopes()
            ->whereIn('declaration_id', $declarationIds->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (DgAuditLog $audit): string => (string) $audit->declaration_id)
            ->map(fn (Collection $audits): ?DgAuditLog => $audits->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQueueRow(ContentDeclaration $declaration, ?DgAuditLog $latestAudit): array
    {
        $shipment = $declaration->shipment instanceof Shipment ? $declaration->shipment : null;
        $account = $shipment?->account instanceof Account ? $shipment->account : null;
        $owner = $account ? $this->resolveExternalOwner($account) : null;
        $review = $this->reviewState($declaration);
        $legal = $this->legalSummary($declaration);

        return [
            'declaration' => $declaration,
            'shipmentReference' => $shipment?->reference_number ?: 'سياق الشحنة غير متاح',
            'shipmentStatus' => $shipment ? $this->shipmentStatusLabel((string) ($shipment->status ?? '')) : 'غير متاح',
            'accountLabel' => $account?->name ?: 'سياق الحساب غير متاح',
            'accountTypeLabel' => $account ? $this->accountTypeLabel((string) $account->type) : 'حساب غير معروف',
            'organizationSummary' => $account?->isOrganization()
                ? trim((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: 'ملف المنظمة غير متاح'))
                : null,
            'ownerSummary' => $owner ? trim((string) $owner->name . ($owner->email ? ' • ' . $owner->email : '')) : 'ملخص المالك غير متاح',
            'statusLabel' => $this->declarationStatusLabel((string) $declaration->status),
            'reviewLabel' => $review['label'],
            'reviewDetail' => $review['detail'],
            'declarationSummary' => $this->queueDeclarationSummary($declaration),
            'legalSummary' => $legal['state_label'] . ' • ' . $legal['detail'],
            'latestAuditSummary' => $this->auditSummaryLine($latestAudit),
        ];
    }

    private function resolveDeclaration(string $declaration): ContentDeclaration
    {
        return ContentDeclaration::query()
            ->withoutGlobalScopes()
            ->with($this->declarationRelations())
            ->findOrFail($declaration);
    }

    private function resolveExternalOwner(Account $account): ?User
    {
        $users = $account->users instanceof Collection
            ? $account->users
            : $account->users()->withoutGlobalScopes()->get();

        return $users
            ->filter(function (User $user): bool {
                if (Schema::hasColumn('users', 'user_type') && $user->user_type !== 'external') {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (User $user): int => (int) ($user->is_owner ?? false))
            ->sortBy(fn (User $user): string => (string) ($user->name ?? ''))
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function shipmentSummary(Shipment $shipment): array
    {
        return [
            'id' => (string) $shipment->id,
            'reference' => (string) ($shipment->reference_number ?: $shipment->id),
            'workflow_status' => $this->shipmentStatusLabel((string) ($shipment->status ?? '')),
            'source' => $this->sourceLabel((string) ($shipment->source ?? 'direct')),
            'dangerous_goods' => $this->yesNo((bool) ($shipment->has_dangerous_goods ?? false)),
            'status_reason' => Str::limit(trim((string) ($shipment->status_reason ?? 'لا توجد ملاحظة مسجلة على سير العمل.')), 140, '...'),
            'created_at' => optional($shipment->created_at)->format('Y-m-d H:i') ?? '-',
            'updated_at' => optional($shipment->updated_at)->format('Y-m-d H:i') ?? '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function accountSummary(Account $account): array
    {
        $owner = $this->resolveExternalOwner($account);

        return [
            'id' => (string) $account->id,
            'name' => (string) $account->name,
            'slug' => (string) ($account->slug ?? '-'),
            'type' => $this->accountTypeLabel((string) $account->type),
            'status' => $this->headline((string) ($account->status ?? 'pending')),
            'organization' => $account->isOrganization()
                ? trim((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: 'ملف المنظمة غير متاح'))
                : 'حساب فردي',
            'owner' => $owner ? (string) ($owner->name ?: $owner->email) : 'ملخص المالك غير متاح',
            'owner_email' => $owner?->email ?: 'لا يوجد بريد مالك ظاهر',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function declarationSummary(ContentDeclaration $declaration): array
    {
        $review = $this->reviewState($declaration);

        return [
            'status' => $this->declarationStatusLabel((string) $declaration->status),
            'review_state' => $review['label'],
            'review_detail' => $review['detail'],
            'dg_answered' => $this->yesNo((bool) ($declaration->dg_flag_declared ?? false)),
            'contains_dg' => $this->yesNo((bool) $declaration->contains_dangerous_goods),
            'declared_at' => optional($declaration->declared_at)->format('Y-m-d H:i') ?? '-',
            'updated_at' => optional($declaration->updated_at)->format('Y-m-d H:i') ?? '-',
            'hold_reason' => $declaration->hold_reason
                ? Str::limit(trim((string) $declaration->hold_reason), 180, '...')
                : 'لا يوجد سبب حجز مسجل.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function legalSummary(ContentDeclaration $declaration): array
    {
        if ((bool) $declaration->contains_dangerous_goods) {
            return [
                'state_label' => 'غير منطبق',
                'detail' => 'إقرارات المواد الخطرة تنتقل إلى مراجعة يدوية بدلًا من مسار الإقرار القانوني.',
                'version' => 'لا توجد نسخة إقرار قانوني مرتبطة',
                'accepted_at' => '-',
                'locale' => (string) ($declaration->locale ?: '-'),
            ];
        }

        if ((bool) $declaration->waiver_accepted) {
            return [
                'state_label' => 'تم القبول',
                'detail' => 'تم تسجيل الإقرار القانوني لهذا التصريح غير الخطر.',
                'version' => (string) ($declaration->waiverVersion?->version ?: 'الإصدار غير متاح'),
                'accepted_at' => optional($declaration->waiver_accepted_at)->format('Y-m-d H:i') ?? '-',
                'locale' => (string) (($declaration->waiverVersion?->locale ?: $declaration->locale) ?: '-'),
            ];
        }

        return [
            'state_label' => 'معلّق',
            'detail' => 'ما زال الإقرار القانوني يحتاج إلى قبول قبل أن تتمكن الشحنة من المتابعة بشكل طبيعي.',
            'version' => (string) ($declaration->waiverVersion?->version ?: 'لا توجد نسخة إقرار قانوني مرتبطة'),
            'accepted_at' => '-',
            'locale' => (string) (($declaration->waiverVersion?->locale ?: $declaration->locale) ?: '-'),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function dgMetadataSummary(?DgMetadata $metadata): ?array
    {
        if (! $metadata instanceof DgMetadata) {
            return null;
        }

        return [
            'un_number' => trim((string) ($metadata->un_number ?: 'غير مسجل')),
            'dg_class' => trim((string) ($metadata->dg_class ?: 'غير مسجل')),
            'packing_group' => trim((string) ($metadata->packing_group ?: 'غير مسجل')),
            'proper_shipping_name' => trim((string) ($metadata->proper_shipping_name ?: 'غير مسجل')),
            'quantity' => $metadata->quantity !== null
                ? rtrim(rtrim(number_format((float) $metadata->quantity, 3, '.', ''), '0'), '.') . ' ' . trim((string) ($metadata->quantity_unit ?: ''))
                : 'غير مسجل',
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    private function workflowSummary(ContentDeclaration $declaration, ?Shipment $shipment): array
    {
        $requiresDisclaimer = (bool) (
            $declaration->dg_flag_declared
            && ! $declaration->contains_dangerous_goods
            && ! $declaration->waiver_accepted
        );

        return [
            'shipment_workflow_state' => $shipment ? $this->shipmentStatusLabel((string) ($shipment->status ?? '')) : 'غير متاح',
            'is_blocked' => $declaration->isBlocked(),
            'declaration_complete' => $declaration->isReadyForIssuance(),
            'requires_disclaimer' => $requiresDisclaimer,
            'next_action' => $this->nextActionForDeclaration($declaration),
        ];
    }

    /**
     * @param Collection<int, array<string, string>> $auditEntries
     * @return Collection<int, array{source: string, detail: string}>
     */
    private function notesSummary(ContentDeclaration $declaration, ?Shipment $shipment, Collection $auditEntries): Collection
    {
        $notes = collect();

        if (trim((string) ($declaration->hold_reason ?? '')) !== '') {
            $notes->push([
                'source' => 'سبب الحجز',
                'detail' => Str::limit(trim((string) $declaration->hold_reason), 180, '...'),
            ]);
        }

        $review = $this->reviewState($declaration);
        if (trim((string) ($review['detail'] ?? '')) !== '') {
            $notes->push([
                'source' => 'ملخص المراجعة',
                'detail' => trim((string) $review['detail']),
            ]);
        }

        if ($shipment instanceof Shipment && trim((string) ($shipment->status_reason ?? '')) !== '') {
            $notes->push([
                'source' => 'ملاحظة سير عمل الشحنة',
                'detail' => Str::limit(trim((string) $shipment->status_reason), 180, '...'),
            ]);
        }

        $auditEntries
            ->filter(static fn (array $entry): bool => trim((string) ($entry['note'] ?? '')) !== '')
            ->take(3)
            ->each(function (array $entry) use ($notes): void {
                $notes->push([
                    'source' => 'ملاحظة تدقيق',
                    'detail' => trim((string) $entry['note']),
                ]);
            });

        return $notes->unique(fn (array $note): string => $note['source'] . '|' . $note['detail'])->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRestrictionSummary(?Account $account): ?array
    {
        if (! $account instanceof Account) {
            return null;
        }

        $account->loadMissing('kycVerification');

        $verification = $account->kycVerification;
        $status = trim((string) (
            $verification?->status
            ?: AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''))
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
            $restrictionNames = VerificationRestriction::getForStatus($status)
                ->pluck('name')
                ->filter(static fn ($name): bool => is_string($name) && trim($name) !== '')
                ->map(static fn (string $name): string => trim($name))
                ->values()
                ->all();
        }

        return [
            'status_label' => (string) ($display['label'] ?? $this->headline($status)),
            'queue_summary' => (string) $effect['queue_summary'],
            'shipping_label' => (string) $effect['shipping_label'],
            'shipping_detail' => (string) $effect['shipping_detail'],
            'international_label' => (string) $effect['international_label'],
            'international_detail' => (string) $effect['international_detail'],
            'action_label' => (string) $effect['action_label'],
            'action_detail' => (string) $effect['action_detail'],
            'blocked_shipments_count' => (int) $effect['blocked_shipments_count'],
            'restriction_names' => $restrictionNames,
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function auditEntries(ContentDeclaration $declaration): Collection
    {
        $declaration->loadMissing(['dgMetadata', 'waiverVersion']);

        return DgAuditLog::query()
            ->withoutGlobalScopes()
            ->forShipment((string) $declaration->shipment_id)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (DgAuditLog $audit): array => [
                'action' => $this->auditActionLabel((string) $audit->action),
                'created_at' => optional($audit->created_at)->format('Y-m-d H:i') ?? '-',
                'actor_role' => $audit->actor_role ? $this->headline((string) $audit->actor_role) : 'النظام',
                'note' => Str::limit(trim((string) ($audit->notes ?: 'لا توجد ملاحظة مسجلة لهذا الحدث الامتثالي.')), 160, '...'),
                'change_summary' => $this->auditChangeSummary($audit, $declaration),
            ])
            ->values();
    }

    private function auditChangeSummary(DgAuditLog $audit, ContentDeclaration $declaration): string
    {
        $parts = [];
        $oldValues = is_array($audit->old_values) ? $audit->old_values : [];
        $newValues = is_array($audit->new_values) ? $audit->new_values : [];

        $oldStatus = $this->auditStatusLabel($oldValues['status'] ?? null);
        $newStatus = $this->auditStatusLabel($newValues['status'] ?? null);

        if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus) {
            $parts[] = sprintf('الحالة: %s -> %s', $oldStatus, $newStatus);
        } elseif ($newStatus !== '') {
            $parts[] = sprintf('الحالة: %s', $newStatus);
        }

        if (array_key_exists('contains_dangerous_goods', $newValues)) {
            $parts[] = 'إجابة المواد الخطرة: ' . $this->yesNo((bool) $newValues['contains_dangerous_goods']);
        } elseif (array_key_exists('contains_dangerous_goods', $oldValues)) {
            $parts[] = 'إجابة المواد الخطرة: ' . $this->yesNo((bool) $oldValues['contains_dangerous_goods']);
        }

        $waiverVersion = trim((string) ($newValues['waiver_version'] ?? ''));
        if ($waiverVersion !== '') {
            $parts[] = 'نسخة الإقرار القانوني: ' . $waiverVersion;
        }

        if ((string) $audit->action === DgAuditLog::ACTION_DG_METADATA_SAVED) {
            $dgMetadataSummary = $this->dgMetadataSummary($declaration->dgMetadata);

            if ($dgMetadataSummary) {
                $metadataParts = array_filter([
                    trim((string) ($dgMetadataSummary['un_number'] ?? '')),
                    trim((string) ($dgMetadataSummary['dg_class'] ?? '')),
                    trim((string) ($dgMetadataSummary['packing_group'] ?? '')),
                ]);

                if ($metadataParts !== []) {
                    $parts[] = 'بيانات المواد الخطرة: ' . implode(' / ', $metadataParts);
                }
            }
        }

        if ($parts === []) {
            return match ((string) $audit->action) {
                DgAuditLog::ACTION_CREATED => 'تم فتح حالة الإقرار.',
                DgAuditLog::ACTION_DG_FLAG_SET => 'إجابة المواد الخطرة: ' . $this->yesNo((bool) $declaration->contains_dangerous_goods),
                DgAuditLog::ACTION_WAIVER_ACCEPTED => 'نسخة الإقرار القانوني: ' . trim((string) ($declaration->waiverVersion?->version ?: 'الإصدار غير متاح')),
                DgAuditLog::ACTION_HOLD_APPLIED => 'الحالة: ' . $this->declarationStatusLabel((string) $declaration->status),
                DgAuditLog::ACTION_COMPLETED => 'الحالة: ' . $this->declarationStatusLabel(ContentDeclaration::STATUS_COMPLETED),
                default => '',
            };
        }

        return implode(' | ', array_values(array_unique($parts)));
    }

    private function auditStatusLabel(mixed $value): string
    {
        $status = trim((string) $value);

        if ($status === '') {
            return '';
        }

        return $this->declarationStatusLabel($status);
    }

    private function nextActionForDeclaration(ContentDeclaration $declaration): string
    {
        if (! $declaration->dg_flag_declared) {
            return 'حدّد ما إذا كانت الشحنة تحتوي على مواد خطرة قبل أن يتمكن سير العمل من المتابعة.';
        }

        if ($declaration->contains_dangerous_goods) {
            return 'تم التصريح بوجود مواد خطرة، لذا تبقى الشحنة في المراجعة اليدوية حتى يعالج الفريق الداخلي سبب الحجز.';
        }

        if (! $declaration->waiver_accepted) {
            return 'اقبل الإقرار القانوني لهذا التصريح غير الخطر قبل أن تتمكن الشحنة من المتابعة بشكل طبيعي.';
        }

        if ($declaration->status === ContentDeclaration::STATUS_COMPLETED) {
            return 'اكتمل حاجز الإقرار. تصبح الشحنة جاهزة للمرحلة التالية من سير العمل عند خلو الفحوصات اللاحقة من العوائق.';
        }

        return 'راجع الحالة الحالية لملف الامتثال قبل دفع الشحنة إلى الأمام.';
    }

    /**
     * @return array{label: string, detail: string}
     */
    private function reviewState(ContentDeclaration $declaration): array
    {
        return match ((string) $declaration->status) {
            ContentDeclaration::STATUS_HOLD_DG => [
                'label' => 'مراجعة يدوية للمواد الخطرة',
                'detail' => $declaration->hold_reason
                    ? Str::limit(trim((string) $declaration->hold_reason), 140, '...')
                    : 'تم التصريح بوجود مواد خطرة، لذلك تم إيقاف تقدم الشحنة الآلي.',
            ],
            ContentDeclaration::STATUS_REQUIRES_ACTION => [
                'label' => 'تحتاج إجراء',
                'detail' => 'يحتاج هذا الإقرار إلى متابعة قبل أن تتمكن الشحنة من المتابعة بشكل طبيعي.',
            ],
            ContentDeclaration::STATUS_COMPLETED => [
                'label' => 'جاهز للمرحلة التالية',
                'detail' => 'اكتمل حاجز الإقرار لسير عمل هذه الشحنة.',
            ],
            ContentDeclaration::STATUS_EXPIRED => [
                'label' => 'منتهي',
                'detail' => 'انتهت صلاحية الإقرار ويجب تحديثه قبل أي معالجة إضافية.',
            ],
            default => [
                'label' => 'مراجعة مفتوحة',
                'detail' => 'ما زال الإقرار بانتظار إجابة نهائية أو قبول الإقرار القانوني.',
            ],
        };
    }

    private function reviewBucket(ContentDeclaration $declaration): string
    {
        return match ((string) $declaration->status) {
            ContentDeclaration::STATUS_HOLD_DG,
            ContentDeclaration::STATUS_REQUIRES_ACTION => 'attention',
            ContentDeclaration::STATUS_COMPLETED => 'clear',
            default => 'open',
        };
    }

    private function waiverPending(ContentDeclaration $declaration): bool
    {
        return (bool) $declaration->dg_flag_declared
            && ! (bool) $declaration->contains_dangerous_goods
            && ! (bool) $declaration->waiver_accepted;
    }

    private function queueDeclarationSummary(ContentDeclaration $declaration): string
    {
        return sprintf(
            'التصريح بالمواد الخطرة: %s • تحتوي مواد خطرة: %s • الإقرار القانوني: %s',
            $this->yesNo((bool) ($declaration->dg_flag_declared ?? false)),
            $this->yesNo((bool) $declaration->contains_dangerous_goods),
            $this->legalSummary($declaration)['state_label']
        );
    }

    private function auditSummaryLine(?DgAuditLog $audit): string
    {
        if (! $audit instanceof DgAuditLog) {
            return 'لا يوجد إدخال تدقيق امتثالي ظاهر لهذه الحالة حتى الآن.';
        }

        $timestamp = optional($audit->created_at)->format('Y-m-d H:i') ?? '-';
        $note = trim((string) ($audit->notes ?: ''));

        if ($note !== '') {
            return sprintf(
                'آخر تحديث: %s • %s • %s',
                $this->auditActionLabel((string) $audit->action),
                $timestamp,
                Str::limit($note, 80, '...')
            );
        }

        return sprintf(
            'آخر تحديث: %s • %s',
            $this->auditActionLabel((string) $audit->action),
            $timestamp
        );
    }

    private function canViewAccount(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('accounts.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function canViewShipment(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('shipments.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DETAIL);
    }

    private function canViewKyc(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('kyc.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_DETAIL);
    }

    private function canViewBilling(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('wallet.balance')
            && $user->hasPermission('wallet.ledger')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_BILLING_DETAIL);
    }

    private function canManageComplianceActions(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('compliance.manage')
            && $user->hasPermission('dg.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_ACTIONS);
    }

    private function hasBillingContext(?Account $account): bool
    {
        if (! $account instanceof Account) {
            return false;
        }

        if (Schema::hasTable('billing_wallets') && $account->billingWallet()->withoutGlobalScopes()->exists()) {
            return true;
        }

        return Schema::hasTable('wallets')
            && method_exists($account, 'wallet')
            && $account->wallet()->withoutGlobalScopes()->exists();
    }

    private function linkedPreflightHold(?Shipment $shipment, ?Account $account): ?WalletHold
    {
        if (! $shipment instanceof Shipment || ! $account instanceof Account) {
            return null;
        }

        if (! Schema::hasTable('wallet_holds') || ! Schema::hasColumn('shipments', 'balance_reservation_id')) {
            return null;
        }

        $hold = $shipment->balanceReservation instanceof WalletHold
            ? $shipment->balanceReservation
            : $shipment->balanceReservation()->withoutGlobalScopes()->first();

        if (! $hold instanceof WalletHold) {
            return null;
        }

        return (string) $hold->account_id === (string) $account->id
            ? $hold
            : null;
    }

    /**
     * @return array{is_available: bool, headline: string, detail: string}
     */
    private function requestCorrectionAction(ContentDeclaration $declaration): array
    {
        if ((bool) $declaration->contains_dangerous_goods || $declaration->status === ContentDeclaration::STATUS_HOLD_DG) {
            return [
                'is_available' => false,
                'headline' => 'حجز المواد الخطرة مفعّل بالفعل',
                'detail' => 'هذه الحالة محجوبة بالفعل لمعالجة المواد الخطرة يدويًا، لذلك لا يُستخدم إجراء طلب التصحيح الداخلي هنا.',
            ];
        }

        if ($declaration->status === ContentDeclaration::STATUS_REQUIRES_ACTION) {
            return [
                'is_available' => false,
                'headline' => 'بانتظار التصحيح بالفعل',
                'detail' => 'يوجد طلب تصحيح نشط بالفعل لهذا الإقرار. راجع السبب الحالي في سجل التدقيق قبل اتخاذ أي إجراء إضافي.',
            ];
        }

        return [
            'is_available' => true,
            'headline' => 'طلب تصحيح من العميل',
            'detail' => 'استخدم هذا الإجراء فقط عندما يجب أن يبقى الإقرار محجوبًا حتى يصحح العميل معلومات الامتثال أو يوضحها. يلزم سبب بشري واضح مع إبقاء سير عمل الشحنة متزامنًا.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            ContentDeclaration::STATUS_PENDING => 'قيد الانتظار',
            ContentDeclaration::STATUS_COMPLETED => 'مكتمل',
            ContentDeclaration::STATUS_HOLD_DG => 'حجز مواد خطرة',
            ContentDeclaration::STATUS_REQUIRES_ACTION => 'تحتاج إجراء',
            ContentDeclaration::STATUS_EXPIRED => 'منتهي',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function reviewOptions(): array
    {
        return [
            'attention' => 'تحتاج متابعة',
            'open' => 'مراجعة مفتوحة',
            'clear' => 'واضحة',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        return array_keys($this->statusOptions());
    }

    private function declarationStatusLabel(string $status): string
    {
        return $this->statusOptions()[$status] ?? $this->headline($status);
    }

    private function shipmentStatusLabel(string $status): string
    {
        return $this->headline($status);
    }

    private function accountTypeLabel(string $type): string
    {
        return $type === 'organization' ? 'حساب منظمة' : 'حساب فردي';
    }

    private function auditActionLabel(string $action): string
    {
        return match ($action) {
            DgAuditLog::ACTION_CREATED => 'تم إنشاء الإقرار',
            DgAuditLog::ACTION_DG_FLAG_SET => 'تم تسجيل إجابة المواد الخطرة',
            DgAuditLog::ACTION_WAIVER_ACCEPTED => 'تم قبول الإقرار القانوني',
            DgAuditLog::ACTION_HOLD_APPLIED => 'تم تطبيق حجز المواد الخطرة',
            DgAuditLog::ACTION_DG_METADATA_SAVED => 'تم حفظ بيانات المواد الخطرة',
            DgAuditLog::ACTION_COMPLETED => 'اكتمل الإقرار',
            DgAuditLog::ACTION_VIEWED => 'تمت المشاهدة',
            DgAuditLog::ACTION_STATUS_CHANGED => 'تم تغيير الحالة',
            default => $this->headline($action),
        };
    }

    private function statusOrderExpression(): string
    {
        return sprintf(
            "case status when '%s' then 0 when '%s' then 1 when '%s' then 2 when '%s' then 3 when '%s' then 4 else 5 end",
            ContentDeclaration::STATUS_HOLD_DG,
            ContentDeclaration::STATUS_REQUIRES_ACTION,
            ContentDeclaration::STATUS_PENDING,
            ContentDeclaration::STATUS_EXPIRED,
            ContentDeclaration::STATUS_COMPLETED,
        );
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'نعم' : 'لا';
    }

    private function headline(string $value): string
    {
        $normalized = trim(str_replace(['_', '-'], ' ', $value));

        if ($normalized === '') {
            return 'غير متاح';
        }

        $map = [
            'active' => 'نشط',
            'approved' => 'مقبول',
            'completed' => 'مكتمل',
            'direct' => 'مباشر',
            'expired' => 'منتهي',
            'external' => 'خارجي',
            'hold dg' => 'حجز مواد خطرة',
            'individual' => 'فردي',
            'internal' => 'داخلي',
            'open' => 'مفتوح',
            'order' => 'طلب',
            'organization' => 'منظمة',
            'pending' => 'قيد الانتظار',
            'requires action' => 'تحتاج إجراء',
            'return' => 'إرجاع',
            'suspended' => 'معلّق',
            'system' => 'النظام',
            'under review' => 'قيد المراجعة',
        ];

        $key = Str::of($normalized)->lower()->value();

        return $map[$key] ?? Str::title($normalized);
    }

    private function sourceLabel(string $source): string
    {
        return match (trim($source)) {
            Shipment::SOURCE_DIRECT => 'مباشر',
            Shipment::SOURCE_ORDER => 'طلب',
            Shipment::SOURCE_BULK => 'مجمع',
            Shipment::SOURCE_RETURN => 'إرجاع',
            default => $this->headline($source),
        };
    }

    private function normalizedFilter(string $value, array $allowed): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : '';
    }
}
