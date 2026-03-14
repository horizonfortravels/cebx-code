<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * WalletBillingService
 *
 * FR-IAM-017: Separate wallet/billing permissions
 * FR-IAM-019: RBAC for wallet (top-up, view ledger, view balance, configure threshold)
 * FR-IAM-020: Mask payment card data when account disabled
 *
 * Wallet Permissions (granular):
 * - wallet:balance     â†’ View balance only
 * - wallet:ledger      â†’ View ledger/transactions
 * - wallet:topup       â†’ Initiate top-up
 * - wallet:configure   â†’ Set threshold, manage wallet settings
 * - billing:view       â†’ View payment methods
 * - billing:manage     â†’ Add/remove payment methods
 * Access is strictly permission-based (deny by default).
 */
class WalletBillingService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Wallet Operations
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ

    /**
     * Get or create wallet for an account (auto-created on first access).
     */
    public function getWallet(string $accountId, User $performer): array
    {
        $canViewBalance = $this->canPerform($performer, 'wallet.balance');
        $wallet = $this->ensureWallet($accountId);

        $this->auditService->info(
            $accountId, $performer->id,
            'wallet.viewed', AuditLog::CATEGORY_FINANCIAL,
            'Wallet', $wallet->id
        );

        return $wallet->summary($canViewBalance);
    }

    /**
     * Get ledger entries (paginated).
     */
    public function getLedger(string $accountId, User $performer, array $filters = []): array
    {
        $this->assertPermission($performer, 'wallet.ledger');

        $wallet = $this->ensureWallet($accountId);
        $query = $wallet->ledgerEntries();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $entries = $query->limit($filters['limit'] ?? 50)->get();

        $this->auditService->info(
            $accountId, $performer->id,
            'wallet.ledger_viewed', AuditLog::CATEGORY_FINANCIAL,
            'Wallet', $wallet->id,
            null, null,
            ['entries_returned' => $entries->count(), 'filters' => $filters]
        );

        return [
            'wallet'  => $wallet->summary(true),
            'entries' => $entries->map(fn ($e) => [
                'id'              => $e->id,
                'type'            => $e->type,
                'type_label'      => $e->typeLabel(),
                'amount'          => $e->amount,
                'running_balance' => $e->running_balance,
                'reference_type'  => $e->reference_type,
                'reference_id'    => $e->reference_id,
                'description'     => $e->description,
                'actor_user_id'   => $e->actor_user_id,
                'created_at'      => $e->created_at?->toISOString(),
            ])->toArray(),
        ];
    }

    /**
     * Record a top-up (credit).
     */
    public function recordTopUp(
        string $accountId,
        float  $amount,
        string $referenceId,
        User   $performer,
        ?string $description = null,
        ?array $metadata = null
    ): WalletLedgerEntry {
        $this->assertPermission($performer, 'wallet.topup');

        if ($amount <= 0) {
            throw new BusinessException('ط§ظ„ظ…ط¨ظ„ط؛ ظٹط¬ط¨ ط£ظ† ظٹظƒظˆظ† ط£ظƒط¨ط± ظ…ظ† طµظپط±.', 'ERR_INVALID_AMOUNT', 422);
        }

        $wallet = $this->ensureWallet($accountId);

        if (!$wallet->isActive()) {
            throw new BusinessException('ط§ظ„ظ…ط­ظپط¸ط© ظ…ط¬ظ…ط¯ط© ط£ظˆ ظ…ط؛ظ„ظ‚ط©.', 'ERR_WALLET_FROZEN', 422);
        }

        return DB::transaction(function () use ($wallet, $amount, $referenceId, $performer, $description, $metadata) {
            $wallet->increment('available_balance', $amount);
            $wallet->refresh();

            $entry = WalletLedgerEntry::create(
                $this->buildLedgerPayload(
                    walletId: (string) $wallet->id,
                    type: WalletLedgerEntry::TYPE_TOPUP,
                    amount: (float) $amount,
                    runningBalance: (float) $wallet->available_balance,
                    referenceType: 'topup',
                    referenceId: $referenceId,
                    actorUserId: (string) $performer->id,
                    description: $description ?? 'شحن رصيد',
                    metadata: $metadata
                )
            );

            $this->auditService->info(
                $wallet->account_id, $performer->id,
                'wallet.topup', AuditLog::CATEGORY_FINANCIAL,
                'WalletLedgerEntry', $entry->id,
                ['balance_before' => $wallet->available_balance - $amount],
                ['balance_after' => $wallet->available_balance, 'amount' => $amount],
                ['reference_id' => $referenceId]
            );

            return $entry;
        });
    }

    /**
     * Record a debit (charge for shipment, etc.)
     */
    public function recordDebit(
        string $accountId,
        float  $amount,
        string $referenceType,
        string $referenceId,
        User   $performer,
        ?string $description = null
    ): WalletLedgerEntry {
        $wallet = $this->ensureWallet($accountId);

        if (!$wallet->isActive()) {
            throw new BusinessException('ط§ظ„ظ…ط­ظپط¸ط© ظ…ط¬ظ…ط¯ط© ط£ظˆ ظ…ط؛ظ„ظ‚ط©.', 'ERR_WALLET_FROZEN', 422);
        }

        if ((float) $wallet->available_balance < $amount) {
            throw new BusinessException('ط±طµظٹط¯ ط§ظ„ظ…ط­ظپط¸ط© ط؛ظٹط± ظƒط§ظپظچ.', 'ERR_INSUFFICIENT_BALANCE', 422);
        }

        return DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $performer, $description) {
            $wallet->decrement('available_balance', $amount);
            $wallet->refresh();

            $entry = WalletLedgerEntry::create(
                $this->buildLedgerPayload(
                    walletId: (string) $wallet->id,
                    type: WalletLedgerEntry::TYPE_DEBIT,
                    amount: (float) (-$amount),
                    runningBalance: (float) $wallet->available_balance,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    actorUserId: (string) $performer->id,
                    description: $description ?? 'خصم',
                    metadata: null
                )
            );

            $this->auditService->info(
                $wallet->account_id, $performer->id,
                'wallet.debit', AuditLog::CATEGORY_FINANCIAL,
                'WalletLedgerEntry', $entry->id,
                ['balance_before' => $wallet->available_balance + $amount],
                ['balance_after' => $wallet->available_balance, 'amount' => -$amount]
            );

            // Check threshold alert
            if ($wallet->isBelowThreshold()) {
                $this->auditService->warning(
                    $wallet->account_id, $performer->id,
                    'wallet.low_balance_alert', AuditLog::CATEGORY_FINANCIAL,
                    'Wallet', $wallet->id,
                    null, null,
                    ['balance' => $wallet->available_balance, 'threshold' => $wallet->low_balance_threshold]
                );
            }

            return $entry;
        });
    }

    /**
     * Configure low-balance threshold.
     */
    public function configureThreshold(string $accountId, ?float $threshold, User $performer): array
    {
        $this->assertPermission($performer, 'wallet.configure');

        $wallet = $this->ensureWallet($accountId);
        $old = $wallet->low_balance_threshold;

        $wallet->update(['low_balance_threshold' => $threshold]);

        $this->auditService->info(
            $accountId, $performer->id,
            'wallet.threshold_updated', AuditLog::CATEGORY_FINANCIAL,
            'Wallet', $wallet->id,
            ['threshold' => $old],
            ['threshold' => $threshold]
        );

        return $wallet->fresh()->summary(true);
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Payment Methods (Billing) â€” FR-IAM-017 + FR-IAM-020
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ

    /**
     * List payment methods (respects FR-IAM-020 masking).
     */
    public function listPaymentMethods(string $accountId, User $performer): array
    {
        $this->assertPermission($performer, 'billing.view');

        $account = Account::findOrFail($accountId);
        $accountDisabled = in_array($account->status, ['suspended', 'closed']);

        $methods = PaymentMethod::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        $this->auditService->info(
            $accountId, $performer->id,
            'billing.methods_viewed', AuditLog::CATEGORY_FINANCIAL,
            null, null, null, null,
            ['count' => $methods->count(), 'account_disabled' => $accountDisabled]
        );

        return $methods->map(fn (PaymentMethod $m) => $m->toSafeArray($accountDisabled))->toArray();
    }

    /**
     * Add a payment method.
     */
    public function addPaymentMethod(string $accountId, array $data, User $performer): PaymentMethod
    {
        $this->assertPermission($performer, 'billing.manage');

        $account = Account::findOrFail($accountId);
        if (in_array($account->status, ['suspended', 'closed'])) {
            throw new BusinessException(
                'ظ„ط§ ظٹظ…ظƒظ† ط¥ط¶ط§ظپط© ظˆط³ظٹظ„ط© ط¯ظپط¹ ظ„ط­ط³ط§ط¨ ظ…ط¹ط·ظ„.',
                'ERR_ACCOUNT_DISABLED', 422
            );
        }

        return DB::transaction(function () use ($accountId, $data, $performer) {
            $isFirst = !PaymentMethod::withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->where('is_active', true)
                ->exists();

            $method = PaymentMethod::create([
                'account_id'          => $accountId,
                'type'                => $data['type'] ?? PaymentMethod::TYPE_CARD,
                'label'               => $data['label'] ?? null,
                'provider'            => $data['provider'] ?? null,
                'last_four'           => $data['last_four'] ?? null,
                'expiry_month'        => $data['expiry_month'] ?? null,
                'expiry_year'         => $data['expiry_year'] ?? null,
                'cardholder_name'     => $data['cardholder_name'] ?? null,
                'gateway_token'       => $data['gateway_token'] ?? null,
                'gateway_customer_id' => $data['gateway_customer_id'] ?? null,
                'is_default'          => $isFirst,
                'is_active'           => true,
                'added_by'            => $performer->id,
            ]);

            $this->auditService->info(
                $accountId, $performer->id,
                'billing.method_added', AuditLog::CATEGORY_FINANCIAL,
                'PaymentMethod', $method->id,
                null,
                ['type' => $method->type, 'provider' => $method->provider, 'last_four' => $method->last_four]
            );

            return $method;
        });
    }

    /**
     * Remove (soft-delete) a payment method.
     */
    public function removePaymentMethod(string $accountId, string $methodId, User $performer): void
    {
        $this->assertPermission($performer, 'billing.manage');

        $method = PaymentMethod::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $methodId)
            ->firstOrFail();

        $method->update(['is_active' => false]);
        $method->delete();

        $this->auditService->warning(
            $accountId, $performer->id,
            'billing.method_removed', AuditLog::CATEGORY_FINANCIAL,
            'PaymentMethod', $method->id,
            ['provider' => $method->provider, 'last_four' => $method->last_four],
            null
        );
    }

    /**
     * FR-IAM-020: Mask all payment methods when account is disabled.
     * Called when account status changes to suspended/closed.
     */
    public function maskPaymentDataForDisabledAccount(string $accountId): int
    {
        $count = PaymentMethod::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('is_masked_override', false)
            ->update([
                'is_masked_override' => true,
                'is_active'          => false,
            ]);

        if ($count > 0) {
            $this->auditService->warning(
                $accountId, null,
                'billing.payment_data_masked', AuditLog::CATEGORY_FINANCIAL,
                'Account', $accountId,
                null,
                ['masked_count' => $count, 'reason' => 'account_disabled']
            );
        }

        return $count;
    }

    /**
     * FR-IAM-020: Restore payment methods on account reactivation.
     * Marks methods for re-validation but doesn't auto-activate.
     */
    public function restorePaymentDataForReactivatedAccount(string $accountId): int
    {
        $count = PaymentMethod::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('is_masked_override', true)
            ->update(['is_masked_override' => false]);

        if ($count > 0) {
            $this->auditService->info(
                $accountId, null,
                'billing.payment_data_restored', AuditLog::CATEGORY_FINANCIAL,
                'Account', $accountId,
                null,
                ['restored_count' => $count, 'note' => 'Methods unmasked but remain inactive until re-validated']
            );
        }

        return $count;
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Supported Permissions List (for RBAC setup)
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ

    public static function walletPermissions(): array
    {
        return [
            'wallet.balance'   => 'ط¹ط±ط¶ ط±طµظٹط¯ ط§ظ„ظ…ط­ظپط¸ط©',
            'wallet.ledger'    => 'ط¹ط±ط¶ ظƒط´ظپ ط§ظ„ط­ط³ط§ط¨',
            'wallet.topup'     => 'ط´ط­ظ† ط§ظ„ط±طµظٹط¯',
            'wallet.configure' => 'ط¥ط¹ط¯ط§ط¯ط§طھ ط§ظ„ظ…ط­ظپط¸ط© (ط­ط¯ ط§ظ„طھظ†ط¨ظٹظ‡)',
            'billing.view'     => 'ط¹ط±ط¶ ظˆط³ط§ط¦ظ„ ط§ظ„ط¯ظپط¹',
            'billing.manage'   => 'ط¥ط¶ط§ظپط©/ط¥ط²ط§ظ„ط© ظˆط³ط§ط¦ظ„ ط§ظ„ط¯ظپط¹',
        ];
    }

    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ
    // Internal Helpers
    // â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ

    private function ensureWallet(string $accountId): Wallet
    {
        $wallet = Wallet::firstOrCreate(
            ['account_id' => $accountId],
            [
                'currency'          => Account::findOrFail($accountId)->currency ?? 'SAR',
                'available_balance' => 0,
                'locked_balance'    => 0,
                'status'            => Wallet::STATUS_ACTIVE,
            ]
        );

        $this->syncBillingWalletCompatibilityRow($wallet);

        return $wallet;
    }

    private function canPerform(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    private function assertPermission(User $user, string $permission): void
    {
        if (!$this->canPerform($user, $permission)) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'wallet.access_denied', AuditLog::CATEGORY_FINANCIAL,
                null, null, null, null,
                ['required_permission' => $permission]
            );
            throw BusinessException::permissionDenied();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLedgerPayload(
        string $walletId,
        string $type,
        float $amount,
        float $runningBalance,
        ?string $referenceType,
        ?string $referenceId,
        ?string $actorUserId,
        ?string $description,
        ?array $metadata
    ): array {
        $payload = [
            'wallet_id' => $walletId,
            'amount' => $amount,
            'running_balance' => $runningBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('wallet_ledger_entries', 'type')) {
            $payload['type'] = $type;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'actor_user_id')) {
            $payload['actor_user_id'] = $actorUserId;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'description')) {
            $payload['description'] = $description;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'transaction_type')) {
            $payload['transaction_type'] = $type;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'direction')) {
            $payload['direction'] = $amount >= 0 ? 'credit' : 'debit';
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'sequence')) {
            $payload['sequence'] = ((int) WalletLedgerEntry::withoutGlobalScopes()
                ->where('wallet_id', $walletId)
                ->max('sequence')) + 1;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'correlation_id')) {
            $payload['correlation_id'] = AuditService::getRequestId() . '-' . Str::lower(Str::random(6));
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'created_by')) {
            $payload['created_by'] = $actorUserId;
        }

        if (Schema::hasColumn('wallet_ledger_entries', 'notes')) {
            $payload['notes'] = $description;
        }

        return $payload;
    }

    private function syncBillingWalletCompatibilityRow(Wallet $wallet): void
    {
        if (!Schema::hasTable('billing_wallets')) {
            return;
        }

        DB::table('billing_wallets')->updateOrInsert(
            ['id' => (string) $wallet->id],
            [
                'account_id' => (string) $wallet->account_id,
                'organization_id' => null,
                'currency' => (string) ($wallet->currency ?? 'SAR'),
                'available_balance' => (float) $wallet->available_balance,
                'reserved_balance' => (float) ($wallet->locked_balance ?? 0),
                'total_credited' => 0,
                'total_debited' => 0,
                'low_balance_threshold' => $wallet->low_balance_threshold,
                'low_balance_notified' => false,
                'low_balance_notified_at' => null,
                'auto_topup_enabled' => false,
                'auto_topup_amount' => null,
                'auto_topup_trigger' => null,
                'status' => (string) ($wallet->status ?? Wallet::STATUS_ACTIVE),
                'allow_negative' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}



