<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Models\WalletTopup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InternalReportExportService
{
    public const DOMAIN_SHIPMENTS = 'shipments';
    public const DOMAIN_KYC = 'kyc';
    public const DOMAIN_BILLING = 'billing';
    public const DOMAIN_COMPLIANCE = 'compliance';
    public const DOMAIN_TICKETS = 'tickets';

    /**
     * @var array<int, string>
     */
    private const SUPPORTED_DOMAINS = [
        self::DOMAIN_SHIPMENTS,
        self::DOMAIN_KYC,
        self::DOMAIN_BILLING,
        self::DOMAIN_COMPLIANCE,
        self::DOMAIN_TICKETS,
    ];

    public function __construct(
        private readonly InternalTicketReadService $ticketReadService,
    ) {}

    /**
     * @return array{domain: string, filename: string, headers: array<int, string>, rows: Collection<int, array<string, string>>, csv: string}
     */
    public function export(string $domain, ?User $user): array
    {
        $domain = strtolower(trim($domain));

        return match ($domain) {
            self::DOMAIN_SHIPMENTS => $this->buildExport($domain, [
                'shipment_reference',
                'account_name',
                'account_slug',
                'account_type',
                'status',
                'carrier',
                'service',
                'tracking_summary',
                'source',
                'international',
                'cod',
                'dangerous_goods',
                'timeline_events',
                'documents_available',
                'created_at',
            ], $this->shipmentRows()),
            self::DOMAIN_KYC => $this->buildExport($domain, [
                'account_name',
                'account_slug',
                'account_type',
                'account_status',
                'kyc_status',
                'submitted_documents',
                'required_documents',
                'blocked_shipments',
                'restricted',
                'shipment_limit',
                'daily_shipment_limit',
                'international_shipping_blocked',
                'review_summary',
                'reviewed_at',
            ], $this->kycRows()),
            self::DOMAIN_BILLING => $this->buildExport($domain, [
                'account_name',
                'account_slug',
                'account_type',
                'wallet_status',
                'wallet_source',
                'currency',
                'current_balance',
                'reserved_balance',
                'available_balance',
                'low_balance',
                'active_holds',
                'topups_confirmed_24h',
                'kyc_status',
                'restriction_summary',
            ], $this->billingRows()),
            self::DOMAIN_COMPLIANCE => $this->buildExport($domain, [
                'shipment_reference',
                'account_name',
                'account_slug',
                'account_type',
                'declaration_status',
                'review_state',
                'dg_declared',
                'dangerous_goods',
                'waiver_status',
                'declared_at',
                'updated_at',
                'restriction_summary',
                'latest_audit_summary',
            ], $this->complianceRows()),
            self::DOMAIN_TICKETS => $this->buildExport($domain, [
                'ticket_number',
                'subject',
                'category',
                'priority',
                'status',
                'account_name',
                'account_slug',
                'linked_shipment_reference',
                'requester_name',
                'assignee_name',
                'recent_activity_summary',
                'replies_count',
                'workflow_activity_summary',
                'updated_at',
            ], $this->ticketRows($user)),
            default => throw new InvalidArgumentException('Unsupported internal report export domain.'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function supportedDomains(): array
    {
        return self::SUPPORTED_DOMAINS;
    }

    /**
     * @param array<int, string> $headers
     * @param Collection<int, array<string, string>> $rows
     * @return array{domain: string, filename: string, headers: array<int, string>, rows: Collection<int, array<string, string>>, csv: string}
     */
    private function buildExport(string $domain, array $headers, Collection $rows): array
    {
        return [
            'domain' => $domain,
            'filename' => sprintf('internal-report-%s-%s.csv', $domain, now()->format('Ymd-His')),
            'headers' => $headers,
            'rows' => $rows,
            'csv' => $this->toCsv($headers, $rows),
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function shipmentRows(): Collection
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->with([
                'account.organizationProfile',
                'carrierShipment',
            ])
            ->withCount([
                'events as timeline_events_count' => static function ($query): void {
                    $query->withoutGlobalScopes();
                },
                'carrierDocuments as available_documents_count' => static function ($query): void {
                    $query->withoutGlobalScopes()->where('is_available', true);
                },
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Shipment $shipment): array {
                $account = $shipment->account;
                $carrierShipment = $shipment->carrierShipment;

                return [
                    'shipment_reference' => $this->valueOrFallback($shipment->reference_number, (string) $shipment->getKey()),
                    'account_name' => $this->valueOrFallback($account?->name),
                    'account_slug' => $this->valueOrFallback($account?->slug),
                    'account_type' => $account instanceof Account && $account->isOrganization() ? 'organization' : 'individual',
                    'status' => $this->headline((string) $shipment->status),
                    'carrier' => $this->valueOrFallback($shipment->carrier_code ?: $carrierShipment?->carrier_code),
                    'service' => $this->valueOrFallback($shipment->service_type ?: $carrierShipment?->service_code),
                    'tracking_summary' => $this->shipmentTrackingSummary($shipment),
                    'source' => $this->headline((string) ($shipment->source ?: 'unknown')),
                    'international' => $this->yesNo((bool) $shipment->is_international),
                    'cod' => $this->yesNo((bool) $shipment->is_cod),
                    'dangerous_goods' => $this->yesNo((bool) $shipment->has_dangerous_goods),
                    'timeline_events' => (string) ((int) ($shipment->timeline_events_count ?? 0)),
                    'documents_available' => (string) ((int) ($shipment->available_documents_count ?? 0)),
                    'created_at' => $this->dateTimeLabel($shipment->created_at),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function kycRows(): Collection
    {
        $restrictedStatuses = $this->restrictedVerificationStatuses();

        return Account::query()
            ->withoutGlobalScopes()
            ->with([
                'organizationProfile',
                'kycVerification.reviewer',
            ])
            ->withCount([
                'shipments as kyc_blocked_shipments_count' => function ($query): void {
                    $query->withoutGlobalScopes()->where('status', Shipment::STATUS_KYC_BLOCKED);
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Account $account) use ($restrictedStatuses): array {
                $verification = $account->kycVerification;
                $status = $this->kycStatusForAccount($account, $verification);
                $capabilities = $verification instanceof KycVerification
                    ? $verification->capabilities()
                    : $this->capabilitiesForStatus($status);

                return [
                    'account_name' => $this->valueOrFallback($account->name),
                    'account_slug' => $this->valueOrFallback($account->slug),
                    'account_type' => $account->isOrganization() ? 'organization' : 'individual',
                    'account_status' => $this->headline((string) ($account->status ?? 'unknown')),
                    'kyc_status' => $this->headline($status),
                    'submitted_documents' => (string) count((array) ($verification?->submitted_documents ?? [])),
                    'required_documents' => (string) count((array) ($verification?->required_documents ?? [])),
                    'blocked_shipments' => (string) ((int) ($account->kyc_blocked_shipments_count ?? 0)),
                    'restricted' => $this->yesNo($this->isRestrictedStatus($status, $restrictedStatuses)),
                    'shipment_limit' => $this->numericOrUnlimited($capabilities['shipping_limit'] ?? null),
                    'daily_shipment_limit' => $this->numericOrUnlimited($capabilities['daily_shipment_limit'] ?? null),
                    'international_shipping_blocked' => $this->yesNo(! (bool) ($capabilities['can_ship_international'] ?? false)),
                    'review_summary' => $this->kycReviewSummary($verification),
                    'reviewed_at' => $this->dateTimeLabel($verification?->reviewed_at),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function billingRows(): Collection
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with([
                'organizationProfile',
                'kycVerification',
                'billingWallet' => static function ($query): void {
                    $query->withoutGlobalScopes()->withCount([
                        'holds as active_holds_count' => static function ($holdQuery): void {
                            $holdQuery->where('status', WalletHold::STATUS_ACTIVE);
                        },
                        'topups as topups_confirmed_24h_count' => static function ($topupQuery): void {
                            $topupQuery->where('status', WalletTopup::STATUS_SUCCESS)
                                ->where('created_at', '>=', now()->subDay());
                        },
                    ]);
                },
            ])
            ->whereHas('billingWallet')
            ->orderBy('name')
            ->get()
            ->map(function (Account $account): array {
                $wallet = $account->billingWallet;
                if (! $wallet instanceof BillingWallet) {
                    return [];
                }

                $kycStatus = $this->kycStatusForAccount($account, $account->kycVerification);

                return [
                    'account_name' => $this->valueOrFallback($account->name),
                    'account_slug' => $this->valueOrFallback($account->slug),
                    'account_type' => $account->isOrganization() ? 'organization' : 'individual',
                    'wallet_status' => $this->headline((string) $wallet->status),
                    'wallet_source' => Schema::hasColumn($wallet->getTable(), 'organization_id') && filled($wallet->organization_id) ? 'organization' : 'account',
                    'currency' => $this->valueOrFallback($wallet->currency),
                    'current_balance' => $this->moneyLabel(((float) $wallet->available_balance) + ((float) $wallet->reserved_balance)),
                    'reserved_balance' => $this->moneyLabel((float) $wallet->reserved_balance),
                    'available_balance' => $this->moneyLabel((float) $wallet->available_balance),
                    'low_balance' => $this->yesNo($wallet->isLowBalance()),
                    'active_holds' => (string) ((int) ($wallet->active_holds_count ?? 0)),
                    'topups_confirmed_24h' => (string) ((int) ($wallet->topups_confirmed_24h_count ?? 0)),
                    'kyc_status' => $this->headline($kycStatus),
                    'restriction_summary' => $this->restrictionSummaryForStatus($kycStatus),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function complianceRows(): Collection
    {
        return ContentDeclaration::query()
            ->withoutGlobalScopes()
            ->with([
                'shipment.account.organizationProfile',
                'shipment.account.kycVerification',
                'auditLogs' => static function ($query): void {
                    $query->withoutGlobalScopes()->latest('created_at');
                },
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ContentDeclaration $declaration): array {
                $shipment = $declaration->shipment;
                $account = $shipment?->account instanceof Account ? $shipment->account : null;
                $latestAudit = $declaration->auditLogs->first();
                $status = $account instanceof Account ? $this->kycStatusForAccount($account, $account->kycVerification) : 'unknown';

                return [
                    'shipment_reference' => $this->valueOrFallback($shipment?->reference_number, $shipment?->getKey()),
                    'account_name' => $this->valueOrFallback($account?->name),
                    'account_slug' => $this->valueOrFallback($account?->slug),
                    'account_type' => $account instanceof Account && $account->isOrganization() ? 'organization' : 'individual',
                    'declaration_status' => $this->headline((string) $declaration->status),
                    'review_state' => $this->complianceReviewState($declaration),
                    'dg_declared' => $this->yesNo((bool) $declaration->dg_flag_declared),
                    'dangerous_goods' => $this->yesNo((bool) $declaration->contains_dangerous_goods),
                    'waiver_status' => $this->waiverStatus($declaration),
                    'declared_at' => $this->dateTimeLabel($declaration->declared_at),
                    'updated_at' => $this->dateTimeLabel($declaration->updated_at),
                    'restriction_summary' => $this->restrictionSummaryForStatus($status),
                    'latest_audit_summary' => $latestAudit instanceof DgAuditLog
                        ? $this->complianceAuditSummary($latestAudit)
                        : 'No compliance audit recorded',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function ticketRows(?User $user): Collection
    {
        return $this->ticketReadService->filteredRows($user, [
            'q' => '',
            'status' => '',
            'priority' => '',
            'category' => '',
            'account_id' => '',
            'shipment_scope' => '',
            'assignee_id' => '',
        ])->map(function (array $row): array {
            return [
                'ticket_number' => $this->valueOrFallback($row['ticket_number'] ?? null),
                'subject' => $this->valueOrFallback($row['subject'] ?? null),
                'category' => $this->valueOrFallback($row['category_label'] ?? null),
                'priority' => $this->valueOrFallback($row['priority_label'] ?? null),
                'status' => $this->valueOrFallback($row['status_label'] ?? null),
                'account_name' => $this->valueOrFallback(data_get($row, 'account_summary.name')),
                'account_slug' => $this->valueOrFallback(data_get($row, 'account_summary.slug')),
                'linked_shipment_reference' => $this->valueOrFallback(data_get($row, 'shipment_summary.reference')),
                'requester_name' => $this->valueOrFallback(data_get($row, 'requester.name')),
                'assignee_name' => $this->valueOrFallback(data_get($row, 'assignee.name')),
                'recent_activity_summary' => $this->valueOrFallback($row['recent_activity_summary'] ?? null),
                'replies_count' => (string) ((int) ($row['replies_count'] ?? 0)),
                'workflow_activity_summary' => $this->valueOrFallback($row['workflow_activity_summary'] ?? null),
                'updated_at' => $this->valueOrFallback($row['updated_at_label'] ?? null),
            ];
        })->values();
    }

    /**
     * @param array<int, string> $headers
     * @param Collection<int, array<string, string>> $rows
     */
    private function toCsv(array $headers, Collection $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(
                static fn (string $header): string => (string) ($row[$header] ?? ''),
                $headers
            ));
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private function shipmentTrackingSummary(Shipment $shipment): string
    {
        $carrierShipment = $shipment->carrierShipment;

        if (
            filled($shipment->tracking_number)
            || filled($shipment->carrier_tracking_number)
            || filled($carrierShipment?->tracking_number)
            || filled($carrierShipment?->awb_number)
        ) {
            return 'Tracking/AWB recorded';
        }

        return 'No tracking summary recorded';
    }

    private function kycStatusForAccount(Account $account, ?KycVerification $verification): string
    {
        if ($verification instanceof KycVerification && filled($verification->status)) {
            return (string) $verification->status;
        }

        if (Schema::hasColumn($account->getTable(), 'kyc_status') && filled($account->kyc_status)) {
            return (string) $account->kyc_status;
        }

        return KycVerification::STATUS_UNVERIFIED;
    }

    /**
     * @return array<int, string>
     */
    private function restrictedVerificationStatuses(): array
    {
        if (! Schema::hasTable('verification_restrictions')) {
            return [];
        }

        return VerificationRestriction::query()
            ->active()
            ->get()
            ->flatMap(static fn (VerificationRestriction $restriction): array => $restriction->applies_to_statuses ?? [])
            ->filter(static fn ($status): bool => is_string($status) && trim($status) !== '')
            ->map(static fn (string $status): string => trim($status))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilitiesForStatus(string $status): array
    {
        $verification = new KycVerification(['status' => $status]);

        return $verification->capabilities();
    }

    /**
     * @param array<int, string> $restrictedStatuses
     */
    private function isRestrictedStatus(string $status, array $restrictedStatuses): bool
    {
        return in_array(trim($status), $restrictedStatuses, true);
    }

    private function kycReviewSummary(?KycVerification $verification): string
    {
        if (! $verification instanceof KycVerification) {
            return 'No internal review recorded';
        }

        if ($verification->reviewed_at !== null) {
            return sprintf(
                'Reviewed %s time(s)%s',
                number_format((int) $verification->review_count),
                $verification->reviewer?->name ? ' by ' . $verification->reviewer->name : ''
            );
        }

        if ($verification->submitted_at !== null) {
            return 'Pending internal review';
        }

        return 'No internal review recorded';
    }

    private function complianceReviewState(ContentDeclaration $declaration): string
    {
        return match ((string) $declaration->status) {
            ContentDeclaration::STATUS_HOLD_DG,
            ContentDeclaration::STATUS_REQUIRES_ACTION => 'Needs attention',
            ContentDeclaration::STATUS_COMPLETED => 'Clear',
            default => 'Open',
        };
    }

    private function waiverStatus(ContentDeclaration $declaration): string
    {
        if ((bool) $declaration->waiver_accepted) {
            return 'Accepted';
        }

        if ((bool) $declaration->dg_flag_declared && ! (bool) $declaration->contains_dangerous_goods) {
            return 'Pending';
        }

        return 'Not required';
    }

    private function complianceAuditSummary(DgAuditLog $audit): string
    {
        return match ((string) $audit->action) {
            DgAuditLog::ACTION_STATUS_CHANGED => 'Status changed',
            DgAuditLog::ACTION_HOLD_APPLIED => 'DG hold applied',
            DgAuditLog::ACTION_DG_FLAG_SET => 'DG flag recorded',
            DgAuditLog::ACTION_WAIVER_ACCEPTED => 'Waiver accepted',
            DgAuditLog::ACTION_DG_METADATA_SAVED => 'DG metadata saved',
            DgAuditLog::ACTION_COMPLETED => 'Declaration completed',
            default => 'Compliance activity recorded',
        };
    }

    private function restrictionSummaryForStatus(string $status): string
    {
        $capabilities = $this->capabilitiesForStatus($status);

        if (! ($capabilities['can_ship_international'] ?? false)) {
            return 'Restrictions active';
        }

        if (($capabilities['shipping_limit'] ?? null) !== null || ($capabilities['daily_shipment_limit'] ?? null) !== null) {
            return 'Shipment limits active';
        }

        return 'Clear';
    }

    private function dateTimeLabel(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $this->valueOrFallback(is_scalar($value) ? (string) $value : null);
        }
    }

    private function valueOrFallback(mixed $value, ?string $fallback = 'N/A'): string
    {
        $normalized = is_scalar($value) ? trim((string) $value) : '';

        return $normalized !== '' ? $normalized : (string) ($fallback ?? 'N/A');
    }

    private function headline(string $value): string
    {
        $normalized = trim($value);

        return $normalized === '' ? 'Unknown' : Str::headline($normalized);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function numericOrUnlimited(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'unlimited';
        }

        return (string) $value;
    }

    private function moneyLabel(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
