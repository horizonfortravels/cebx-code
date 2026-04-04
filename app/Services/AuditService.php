<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Exceptions\BusinessException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * AuditService — Centralized audit logging for the entire system.
 *
 * FR-IAM-006: Comprehensive audit log (append-only, immutable, searchable)
 * FR-IAM-013: Organization/team audit context
 *
 * Every module should use this service instead of directly creating AuditLog records.
 */
class AuditService
{
    /**
     * Current request correlation ID (set once per request).
     */
    private static ?string $requestId = null;

    // ─── Core Logging Method ─────────────────────────────────────

    /**
     * Record an audit entry. This is the primary method.
     *
     * @param string|null $accountId  Tenant account ID (or null for platform-scoped actions)
     * @param string|null $userId     Actor performing the action (null for system actions)
     * @param string      $action     Action identifier (e.g., 'user.created', 'role.updated')
     * @param string      $category   Category constant (e.g., AuditLog::CATEGORY_USERS)
     * @param string      $severity   Severity constant (info/warning/critical)
     * @param string|null $entityType Entity class (e.g., 'User', 'Role', 'Invitation')
     * @param string|null $entityId   Entity UUID
     * @param array|null  $oldValues  Previous values (for change tracking)
     * @param array|null  $newValues  New values
     * @param array|null  $metadata   Additional context (custom key-value data)
     */
    public function log(
        ?string $accountId,
        ?string $userId,
        string  $action,
        string  $category = AuditLog::CATEGORY_SYSTEM,
        string  $severity = AuditLog::SEVERITY_INFO,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array  $oldValues = null,
        ?array  $newValues = null,
        ?array  $metadata = null
    ): AuditLog {
        $payload = [];

        if ($accountId !== null && $this->auditColumnExists('account_id')) {
            $payload['account_id'] = $accountId;
        }

        if ($this->auditColumnExists('user_id')) {
            $payload['user_id'] = $userId;
        }

        if ($this->auditColumnExists('action')) {
            $payload['action'] = $action;
        } elseif ($this->auditColumnExists('event')) {
            $payload['event'] = $action;
        }

        if ($this->auditColumnExists('severity')) {
            $payload['severity'] = $severity;
        }

        if ($this->auditColumnExists('category')) {
            $payload['category'] = $category;
        }

        if ($this->auditColumnExists('entity_type')) {
            $payload['entity_type'] = $entityType;
        } elseif ($this->auditColumnExists('auditable_type')) {
            $payload['auditable_type'] = $entityType;
        }

        if ($this->auditColumnExists('entity_id')) {
            $payload['entity_id'] = $entityId;
        } elseif ($this->auditColumnExists('auditable_id')) {
            $payload['auditable_id'] = $this->normalizeAuditableId($entityId);
        }

        if ($this->auditColumnExists('old_values')) {
            $payload['old_values'] = $oldValues;
        }

        if ($this->auditColumnExists('new_values')) {
            $payload['new_values'] = $newValues;
        }

        if ($this->auditColumnExists('metadata')) {
            $payload['metadata'] = $metadata;
        }

        if ($this->auditColumnExists('ip_address')) {
            $payload['ip_address'] = request()->ip();
        }

        if ($this->auditColumnExists('user_agent')) {
            $payload['user_agent'] = request()->userAgent();
        }

        if ($this->auditColumnExists('request_id')) {
            $payload['request_id'] = self::getRequestId();
        }

        return AuditLog::withoutGlobalScopes()->create($payload);
    }

    // ─── Convenience Methods (semantic shortcuts) ────────────────

    /**
     * Log an informational event (default severity).
     */
    public function info(
        ?string $accountId, ?string $userId, string $action, string $category,
        ?string $entityType = null, ?string $entityId = null,
        ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null
    ): AuditLog {
        return $this->log($accountId, $userId, $action, $category,
            AuditLog::SEVERITY_INFO, $entityType, $entityId, $oldValues, $newValues, $metadata);
    }

    /**
     * Log a warning event (e.g., failed permission check, suspicious activity).
     */
    public function warning(
        ?string $accountId, ?string $userId, string $action, string $category,
        ?string $entityType = null, ?string $entityId = null,
        ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null
    ): AuditLog {
        return $this->log($accountId, $userId, $action, $category,
            AuditLog::SEVERITY_WARNING, $entityType, $entityId, $oldValues, $newValues, $metadata);
    }

    /**
     * Log a critical security event (e.g., owner change, mass delete, breach attempt).
     */
    public function critical(
        ?string $accountId, ?string $userId, string $action, string $category,
        ?string $entityType = null, ?string $entityId = null,
        ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null
    ): AuditLog {
        return $this->log($accountId, $userId, $action, $category,
            AuditLog::SEVERITY_CRITICAL, $entityType, $entityId, $oldValues, $newValues, $metadata);
    }

    // ─── Query Methods ───────────────────────────────────────────

    /**
     * Search audit logs with comprehensive filters.
     */
    public function search(string $accountId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = AuditLog::withoutGlobalScopes()
            ->forAccount($accountId)
            ->with('performer:id,name,email');

        // Filter: category
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        // Filter: severity
        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }

        // Filter: actor (user_id)
        if (!empty($filters['actor_id'])) {
            $query->byActor($filters['actor_id']);
        }

        // Filter: action (exact or prefix match)
        if (!empty($filters['action'])) {
            if (str_contains($filters['action'], '*')) {
                $prefix = str_replace('*', '%', $filters['action']);
                $query->where('action', 'LIKE', $prefix);
            } else {
                $query->byAction($filters['action']);
            }
        }

        // Filter: entity type + optional entity ID
        if (!empty($filters['entity_type'])) {
            $query->byEntity($filters['entity_type'], $filters['entity_id'] ?? null);
        }

        // Filter: date range
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        // Filter: IP address
        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        // Filter: request/correlation ID
        if (!empty($filters['request_id'])) {
            $query->byRequestId($filters['request_id']);
        }

        // Filter: free text search in action or metadata
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("new_values::text ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("metadata::text ILIKE ?", ["%{$search}%"]);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        return $query->orderBy($sortBy, $sortDir)
                     ->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Get audit trail for a specific entity (e.g., all changes to a user).
     */
    public function entityTrail(
        string $accountId,
        string $entityType,
        string $entityId
    ): \Illuminate\Pagination\LengthAwarePaginator {
        return AuditLog::withoutGlobalScopes()
            ->forAccount($accountId)
            ->byEntity($entityType, $entityId)
            ->with('performer:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    }

    /**
     * Get all events in a single request (correlation).
     */
    public function requestTrace(string $accountId, string $requestId): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::withoutGlobalScopes()
            ->forAccount($accountId)
            ->byRequestId($requestId)
            ->with('performer:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get summary statistics for the account's audit log.
     */
    public function statistics(string $accountId, ?string $from = null, ?string $to = null): array
    {
        $query = AuditLog::withoutGlobalScopes()->forAccount($accountId);

        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        $total = (clone $query)->count();

        $bySeverity = (clone $query)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $byCategory = (clone $query)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $topActions = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total'       => $total,
            'by_severity' => $bySeverity,
            'by_category' => $byCategory,
            'top_actions' => $topActions,
        ];
    }

    /**
     * Export audit logs as array (for CSV/JSON generation).
     * Logs the export action itself for compliance.
     */
    public function export(string $accountId, User $performer, array $filters = []): array
    {
        // Log the export action
        $this->info(
            $accountId, $performer->id, 'audit.exported', AuditLog::CATEGORY_EXPORT,
            'AuditLog', null, null, null,
            ['filters' => $filters, 'exported_by' => $performer->email]
        );

        $query = AuditLog::withoutGlobalScopes()
            ->forAccount($accountId)
            ->with('performer:id,name,email');

        // Apply same filters as search
        if (!empty($filters['category'])) $query->byCategory($filters['category']);
        if (!empty($filters['severity'])) $query->bySeverity($filters['severity']);
        if (!empty($filters['actor_id'])) $query->byActor($filters['actor_id']);
        if (!empty($filters['action'])) $query->byAction($filters['action']);
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        // Limit export to 10,000 rows for safety
        return $query->orderBy('created_at', 'desc')
                     ->limit(10000)
                     ->get()
                     ->map(fn ($log) => [
                         'id'          => $log->id,
                         'timestamp'   => $log->created_at?->toISOString(),
                         'actor'       => $log->performer?->name ?? 'System',
                         'actor_email' => $log->performer?->email ?? 'system',
                         'action'      => $log->action,
                         'severity'    => $log->severity,
                         'category'    => $log->category,
                         'entity_type' => $log->entity_type,
                         'entity_id'   => $log->entity_id,
                         'ip_address'  => $log->ip_address,
                         'old_values'  => json_encode($log->old_values, JSON_UNESCAPED_UNICODE),
                         'new_values'  => json_encode($log->new_values, JSON_UNESCAPED_UNICODE),
                     ])
                     ->toArray();
    }

    // ─── Request ID (Correlation) ────────────────────────────────

    /**
     * Get or generate the current request's correlation ID.
     */
    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = (string) Str::uuid();
        }
        return self::$requestId;
    }

    /**
     * Set the request ID (e.g., from incoming header).
     */
    public static function setRequestId(string $id): void
    {
        self::$requestId = $id;
    }

    /**
     * Reset request ID (for testing).
     */
    public static function resetRequestId(): void
    {
        self::$requestId = null;
    }

    // ─── Action Registry ─────────────────────────────────────────

    /**
     * All known audit actions with their category and default severity.
     * Used for documentation and auto-categorization.
     */
    public static function actionRegistry(): array
    {
        return [
            // Auth
            'auth.login'                => ['category' => 'auth', 'severity' => 'info'],
            'auth.logout'               => ['category' => 'auth', 'severity' => 'info'],
            'auth.login_failed'         => ['category' => 'auth', 'severity' => 'warning'],
            'auth.password_changed'     => ['category' => 'auth', 'severity' => 'warning'],
            'auth.2fa_enabled'          => ['category' => 'auth', 'severity' => 'info'],

            // Users
            'user.added'                => ['category' => 'users', 'severity' => 'info'],
            'user.updated'              => ['category' => 'users', 'severity' => 'info'],
            'user.disabled'             => ['category' => 'users', 'severity' => 'warning'],
            'user.enabled'              => ['category' => 'users', 'severity' => 'info'],
            'user.deleted'              => ['category' => 'users', 'severity' => 'critical'],

            // Roles & Permissions
            'role.created'              => ['category' => 'roles', 'severity' => 'info'],
            'role.updated'              => ['category' => 'roles', 'severity' => 'info'],
            'role.deleted'              => ['category' => 'roles', 'severity' => 'warning'],
            'role.assigned'             => ['category' => 'roles', 'severity' => 'info'],
            'role.revoked'              => ['category' => 'roles', 'severity' => 'warning'],
            'permission.denied'         => ['category' => 'roles', 'severity' => 'warning'],

            // Account
            'account.created'           => ['category' => 'account', 'severity' => 'info'],
            'account.updated'           => ['category' => 'account', 'severity' => 'info'],
            'account.type_changed'      => ['category' => 'account', 'severity' => 'critical'],

            // Invitations
            'invitation.created'        => ['category' => 'invitation', 'severity' => 'info'],
            'invitation.accepted'       => ['category' => 'invitation', 'severity' => 'info'],
            'invitation.cancelled'      => ['category' => 'invitation', 'severity' => 'info'],
            'invitation.resent'         => ['category' => 'invitation', 'severity' => 'info'],
            'invitation.email_sent'     => ['category' => 'invitation', 'severity' => 'info'],

            // KYC
            'kyc.submitted'             => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.approved'              => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.rejected'              => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.resubmitted'           => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.expired'               => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.document_uploaded'     => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.documents_listed'      => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.document_accessed'     => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.document_purged'       => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.restriction_updated'   => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.restriction_disabled'  => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.access_denied'         => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.document_access_denied'=> ['category' => 'kyc', 'severity' => 'warning'],

            // Financial
            'financial.view_attempted'  => ['category' => 'financial', 'severity' => 'info'],
            'financial.access_denied'   => ['category' => 'financial', 'severity' => 'warning'],
            'financial.card_masked'     => ['category' => 'financial', 'severity' => 'info'],
            'financial.profit_accessed' => ['category' => 'financial', 'severity' => 'info'],
            'financial.card_accessed'   => ['category' => 'financial', 'severity' => 'info'],

            // Settings (FR-IAM-008)
            'account.settings_updated'       => ['category' => 'settings', 'severity' => 'info'],
            'account.settings_reset'         => ['category' => 'settings', 'severity' => 'info'],
            'account.settings_access_denied' => ['category' => 'settings', 'severity' => 'warning'],

            // Stores (FR-IAM-009)
            'store.created'        => ['category' => 'account', 'severity' => 'info'],
            'store.updated'        => ['category' => 'account', 'severity' => 'info'],
            'store.deleted'        => ['category' => 'account', 'severity' => 'warning'],
            'store.set_default'    => ['category' => 'account', 'severity' => 'info'],
            'store.status_changed' => ['category' => 'account', 'severity' => 'info'],
            'store.access_denied'  => ['category' => 'account', 'severity' => 'warning'],

            // Wallet & Billing (FR-IAM-017/019/020)
            'wallet.viewed'              => ['category' => 'financial', 'severity' => 'info'],
            'wallet.ledger_viewed'       => ['category' => 'financial', 'severity' => 'info'],
            'wallet.topup'               => ['category' => 'financial', 'severity' => 'info'],
            'wallet.debit'               => ['category' => 'financial', 'severity' => 'info'],
            'wallet.threshold_updated'   => ['category' => 'financial', 'severity' => 'info'],
            'wallet.low_balance_alert'   => ['category' => 'financial', 'severity' => 'warning'],
            'wallet.access_denied'       => ['category' => 'financial', 'severity' => 'warning'],
            'billing.methods_viewed'     => ['category' => 'financial', 'severity' => 'info'],
            'billing.method_added'       => ['category' => 'financial', 'severity' => 'info'],
            'billing.method_removed'     => ['category' => 'financial', 'severity' => 'warning'],
            'billing.payment_data_masked'    => ['category' => 'financial', 'severity' => 'warning'],
            'billing.payment_data_restored'  => ['category' => 'financial', 'severity' => 'info'],

            // ST Module: Orders & Store Sync
            'store.connection_tested'  => ['category' => 'account', 'severity' => 'info'],
            'store.webhooks_registered' => ['category' => 'account', 'severity' => 'info'],
            'store.sync_completed'     => ['category' => 'account', 'severity' => 'info'],
            'order.created'            => ['category' => 'account', 'severity' => 'info'],
            'order.status_changed'     => ['category' => 'account', 'severity' => 'info'],
            'order.cancelled'          => ['category' => 'account', 'severity' => 'warning'],
            'order.fulfillment_sent'   => ['category' => 'account', 'severity' => 'info'],
            'order.access_denied'      => ['category' => 'account', 'severity' => 'warning'],

            // SH Module: Shipments (FR-SH-001→019)
            'shipment.created'              => ['category' => 'account', 'severity' => 'info'],
            'shipment.created_from_order'   => ['category' => 'account', 'severity' => 'info'],
            'shipment.status_changed'       => ['category' => 'account', 'severity' => 'info'],
            'shipment.cancelled'            => ['category' => 'account', 'severity' => 'warning'],
            'shipment.label_printed'        => ['category' => 'account', 'severity' => 'info'],
            'shipment.bulk_created'         => ['category' => 'account', 'severity' => 'info'],
            'shipment.return_created'       => ['category' => 'account', 'severity' => 'info'],
            'shipment.parcel_added'         => ['category' => 'account', 'severity' => 'info'],
            'shipment.parcel_removed'       => ['category' => 'account', 'severity' => 'info'],
            'address.created'               => ['category' => 'account', 'severity' => 'info'],
            'address.deleted'               => ['category' => 'account', 'severity' => 'info'],

            // RT Module: Rates & Pricing (FR-RT-001→012)
            'rate.fetched'                  => ['category' => 'account', 'severity' => 'info'],
            'rate.selected'                 => ['category' => 'account', 'severity' => 'info'],
            'rate.expired'                  => ['category' => 'account', 'severity' => 'info'],
            'pricing_rule.created'          => ['category' => 'account', 'severity' => 'info'],
            'pricing_rule.updated'          => ['category' => 'account', 'severity' => 'info'],
            'pricing_rule.deleted'          => ['category' => 'account', 'severity' => 'warning'],

            // CR Module: Carrier Integration (FR-CR-001→008)
            'carrier.shipment_created'      => ['category' => 'carrier', 'severity' => 'info'],
            'carrier.label_refetched'       => ['category' => 'carrier', 'severity' => 'info'],
            'carrier.shipment_cancelled'    => ['category' => 'carrier', 'severity' => 'warning'],
            'carrier.creation_retried'      => ['category' => 'carrier', 'severity' => 'info'],
            'carrier.error_logged'          => ['category' => 'carrier', 'severity' => 'error'],
            'carrier.document_downloaded'   => ['category' => 'carrier', 'severity' => 'info'],

            // TR Module: Tracking (FR-TR-001→007)
            'tracking.webhook_received'     => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.webhook_rejected'     => ['category' => 'tracking', 'severity' => 'warning'],
            'tracking.event_processed'      => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.status_updated'       => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.store_notified'       => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.exception_created'    => ['category' => 'tracking', 'severity' => 'warning'],
            'tracking.exception_acknowledged' => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.exception_resolved'   => ['category' => 'tracking', 'severity' => 'info'],
            'tracking.exception_escalated'  => ['category' => 'tracking', 'severity' => 'warning'],
            'tracking.subscribed'           => ['category' => 'tracking', 'severity' => 'info'],

            // NTF Module: Notifications (FR-NTF-001→009)
            'notification.sent'             => ['category' => 'notification', 'severity' => 'info'],
            'notification.failed'           => ['category' => 'notification', 'severity' => 'warning'],
            'notification.retried'          => ['category' => 'notification', 'severity' => 'info'],
            'notification.template_created' => ['category' => 'notification', 'severity' => 'info'],
            'notification.template_updated' => ['category' => 'notification', 'severity' => 'info'],
            'notification.preferences_updated' => ['category' => 'notification', 'severity' => 'info'],
            'notification.channel_configured'  => ['category' => 'notification', 'severity' => 'info'],
            'notification.schedule_created' => ['category' => 'notification', 'severity' => 'info'],

            // PAY Module: Payments & Subscriptions (FR-PAY-001→011)
            'payment.topup'                 => ['category' => 'payment', 'severity' => 'info'],
            'payment.shipping_charged'      => ['category' => 'payment', 'severity' => 'info'],
            'payment.failed'                => ['category' => 'payment', 'severity' => 'warning'],
            'payment.refunded'              => ['category' => 'payment', 'severity' => 'info'],
            'subscription.created'          => ['category' => 'payment', 'severity' => 'info'],
            'subscription.cancelled'        => ['category' => 'payment', 'severity' => 'warning'],
            'subscription.renewed'          => ['category' => 'payment', 'severity' => 'info'],
            'invoice.generated'             => ['category' => 'payment', 'severity' => 'info'],
            'promo.created'                 => ['category' => 'payment', 'severity' => 'info'],
            'promo.applied'                 => ['category' => 'payment', 'severity' => 'info'],
            'balance.alert_triggered'       => ['category' => 'payment', 'severity' => 'warning'],

            // RPT Module: Reports & Analytics (FR-RPT-001→010)
            'report.generated'              => ['category' => 'report', 'severity' => 'info'],
            'report.exported'               => ['category' => 'report', 'severity' => 'info'],
            'report.export_failed'          => ['category' => 'report', 'severity' => 'warning'],
            'report.scheduled'              => ['category' => 'report', 'severity' => 'info'],
            'report.schedule_cancelled'     => ['category' => 'report', 'severity' => 'info'],
            'report.saved'                  => ['category' => 'report', 'severity' => 'info'],
            'report.financial_accessed'     => ['category' => 'report', 'severity' => 'info'],

            // ADM Module: Platform Administration (FR-ADM-001→010)
            'admin.setting_updated'         => ['category' => 'admin', 'severity' => 'warning'],
            'admin.carrier_tested'          => ['category' => 'admin', 'severity' => 'info'],
            'admin.user_suspended'          => ['category' => 'admin', 'severity' => 'critical'],
            'admin.user_activated'          => ['category' => 'admin', 'severity' => 'warning'],
            'admin.tax_rule_created'        => ['category' => 'admin', 'severity' => 'info'],
            'admin.role_template_created'   => ['category' => 'admin', 'severity' => 'info'],
            'support.ticket_created'        => ['category' => 'support', 'severity' => 'info'],
            'support.ticket_assigned'       => ['category' => 'support', 'severity' => 'info'],
            'support.ticket_status_changed' => ['category' => 'support', 'severity' => 'info'],
            'support.ticket_resolved'       => ['category' => 'support', 'severity' => 'info'],
            'support.ticket_note_added'     => ['category' => 'support', 'severity' => 'info'],
            'admin.api_key_created'         => ['category' => 'admin', 'severity' => 'warning'],
            'admin.api_key_revoked'         => ['category' => 'admin', 'severity' => 'critical'],
            'admin.api_key_rotated'         => ['category' => 'admin', 'severity' => 'warning'],
            'admin.feature_flag_toggled'    => ['category' => 'admin', 'severity' => 'info'],

            // ORG Module: Organizations & Teams (FR-ORG-001→010)
            'org.created'                   => ['category' => 'organization', 'severity' => 'info'],
            'org.profile_updated'           => ['category' => 'organization', 'severity' => 'info'],
            'org.member_invited'            => ['category' => 'organization', 'severity' => 'info'],
            'org.invite_accepted'           => ['category' => 'organization', 'severity' => 'info'],
            'org.invite_cancelled'          => ['category' => 'organization', 'severity' => 'info'],
            'org.ownership_transferred'     => ['category' => 'organization', 'severity' => 'critical'],
            'org.member_suspended'          => ['category' => 'organization', 'severity' => 'warning'],
            'org.member_removed'            => ['category' => 'organization', 'severity' => 'warning'],
            'org.verified'                  => ['category' => 'organization', 'severity' => 'info'],
            'org.rejected'                  => ['category' => 'organization', 'severity' => 'warning'],
            'org.wallet_topup'              => ['category' => 'organization', 'severity' => 'info'],
            'org.wallet_settings_updated'   => ['category' => 'organization', 'severity' => 'info'],

            // BRP Module: Business Rules — Pricing (FR-BRP-001→008)
            'pricing.calculated'            => ['category' => 'pricing', 'severity' => 'info'],
            'pricing.guardrail_applied'     => ['category' => 'pricing', 'severity' => 'warning'],
            'pricing.expired_surcharge'     => ['category' => 'pricing', 'severity' => 'warning'],
            'pricing.rule_set_created'      => ['category' => 'pricing', 'severity' => 'info'],
            'pricing.rule_set_activated'    => ['category' => 'pricing', 'severity' => 'info'],
            'pricing.rounding_set'          => ['category' => 'pricing', 'severity' => 'info'],

            // KYC Module: Compliance & Verification (FR-KYC-001→008)
            'kyc.case_created'              => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.document_uploaded'         => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.case_submitted'            => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.case_approved'             => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.case_rejected'             => ['category' => 'kyc', 'severity' => 'warning'],
            'kyc.document_accessed'         => ['category' => 'kyc', 'severity' => 'info'],
            'kyc.restriction_checked'       => ['category' => 'kyc', 'severity' => 'info'],

            // BW Module: Billing & Wallet (FR-BW-001→010)
            'billing.wallet_created'        => ['category' => 'billing', 'severity' => 'info'],
            'billing.topup_initiated'       => ['category' => 'billing', 'severity' => 'info'],
            'billing.topup_confirmed'       => ['category' => 'billing', 'severity' => 'info'],
            'billing.topup_failed'          => ['category' => 'billing', 'severity' => 'warning'],
            'billing.charge'                => ['category' => 'billing', 'severity' => 'info'],
            'billing.refund'                => ['category' => 'billing', 'severity' => 'info'],
            'billing.hold_created'          => ['category' => 'billing', 'severity' => 'info'],
            'billing.hold_captured'         => ['category' => 'billing', 'severity' => 'info'],
            'billing.hold_released'         => ['category' => 'billing', 'severity' => 'info'],
            'billing.reversal'              => ['category' => 'billing', 'severity' => 'warning'],
            'billing.low_balance_alert'     => ['category' => 'billing', 'severity' => 'warning'],
            'billing.reconciliation'        => ['category' => 'billing', 'severity' => 'info'],

            // DG Module: Dangerous Goods Compliance (FR-DG-001→009)
            'dg.declaration_created'        => ['category' => 'dg', 'severity' => 'info'],
            'dg.dg_flag_set'                => ['category' => 'dg', 'severity' => 'info'],
            'dg.waiver_accepted'            => ['category' => 'dg', 'severity' => 'info'],
            'dg.hold_applied'               => ['category' => 'dg', 'severity' => 'warning'],
            'dg.metadata_saved'             => ['category' => 'dg', 'severity' => 'info'],
            'dg.declaration_completed'      => ['category' => 'dg', 'severity' => 'info'],
            'dg.declaration_viewed'         => ['category' => 'dg', 'severity' => 'info'],
            'dg.audit_exported'             => ['category' => 'dg', 'severity' => 'info'],
            'dg.waiver_published'           => ['category' => 'dg', 'severity' => 'info'],

            // Export
            'audit.exported'            => ['category' => 'export', 'severity' => 'info'],
            'data.exported'             => ['category' => 'export', 'severity' => 'info'],
        ];
    }

    private function auditColumnExists(string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        $cache[$column] = Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', $column);

        return $cache[$column];
    }

    private function normalizeAuditableId(?string $entityId): ?int
    {
        if ($entityId === null) {
            return null;
        }

        $value = trim($entityId);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
