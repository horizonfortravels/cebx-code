<?php

namespace App\Services;

use App\Models\BillingWallet;
use App\Models\ReconciliationReport;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletRefund;
use App\Models\WalletTopup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BillingWalletService — FR-BW-001→010 (10 requirements)
 *
 * FR-BW-001: Auto-create wallet per account/org (balance=0, reference currency)
 * FR-BW-002: Initiate top-up with idempotency, linked to payment gateway
 * FR-BW-003: Manage top-up states (Pending→Success/Failed), update balance only on Success
 * FR-BW-004: Immutable append-only ledger (no UPDATE/DELETE, reversal for corrections)
 * FR-BW-005: Running balance after each entry, statements with filters
 * FR-BW-006: Shipment-linked refunds with idempotency, no over-refund
 * FR-BW-007: Hold/Reservation before label, capture on success, release on failure
 * FR-BW-008: Liquidity threshold alert with rate-limit
 * FR-BW-009: RBAC on wallet/ledger (least privilege, masking, audit)
 * FR-BW-010: Reconciliation reports matching gateway ↔ ledger
 */
class BillingWalletService
{
    // ═══════════════════════════════════════════════════════════
    // FR-BW-001: Create Wallet
    // ═══════════════════════════════════════════════════════════

    public function createWallet(string $accountId, string $currency = 'SAR', ?string $organizationId = null): BillingWallet
    {
        return BillingWallet::firstOrCreate(
            ['account_id' => $accountId, 'currency' => $currency],
            [
                'organization_id'   => $organizationId,
                'available_balance' => 0,
                'reserved_balance'  => 0,
                'total_credited'    => 0,
                'total_debited'     => 0,
                'status'            => 'active',
            ]
        );
    }

    public function getWallet(string $walletId): BillingWallet
    {
        return BillingWallet::findOrFail($walletId);
    }

    public function getWalletForAccount(string $accountId, string $currency = 'SAR'): ?BillingWallet
    {
        return BillingWallet::where('account_id', $accountId)->where('currency', $currency)->first();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-002: Initiate Top-up
    // ═══════════════════════════════════════════════════════════

    public function initiateTopup(string $walletId, float $amount, array $options = []): WalletTopup
    {
        $wallet = BillingWallet::findOrFail($walletId);

        if ($amount <= 0) {
            throw new \RuntimeException('ERR_INVALID_AMOUNT: Amount must be positive');
        }

        $currency = $options['currency'] ?? $wallet->currency;
        if ($currency !== $wallet->currency) {
            throw new \RuntimeException('ERR_CURRENCY_MISMATCH: Currency does not match wallet');
        }

        // Idempotency check
        $idempotencyKey = $options['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = WalletTopup::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;
        }

        return WalletTopup::create([
            'wallet_id'       => $walletId,
            'account_id'      => $wallet->account_id,
            'amount'          => $amount,
            'currency'        => $currency,
            'status'          => WalletTopup::STATUS_PENDING,
            'payment_gateway' => $options['payment_gateway'] ?? null,
            'payment_method'  => $options['payment_method'] ?? null,
            'checkout_url'    => $options['checkout_url'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'initiated_by'    => $options['initiated_by'] ?? null,
            'expires_at'      => now()->addHours(1),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-003: Confirm/Fail Top-up
    // ═══════════════════════════════════════════════════════════

    public function confirmTopup(string $topupId, string $paymentReference, ?array $metadata = null): WalletTopup
    {
        return DB::transaction(function () use ($topupId, $paymentReference, $metadata) {
            $topup = WalletTopup::lockForUpdate()->findOrFail($topupId);

            if (!$topup->isPending()) {
                throw new \RuntimeException('Top-up is not in pending state');
            }

            $topup->confirm($paymentReference, $metadata);

            // Credit wallet
            $wallet = BillingWallet::lockForUpdate()->findOrFail($topup->wallet_id);
            $wallet->increment('available_balance', (float) $topup->amount);
            $wallet->increment('total_credited', (float) $topup->amount);

            // FR-BW-004: Create ledger entry
            $this->createLedgerEntry($wallet, [
                'transaction_type' => 'topup',
                'direction'        => 'credit',
                'amount'           => (float) $topup->amount,
                'reference_type'   => 'topup',
                'reference_id'     => $topup->id,
                'notes'            => 'Top-up confirmed: ' . $paymentReference,
            ]);

            // FR-BW-008: Reset low balance notification
            if (!$wallet->isLowBalance()) {
                $wallet->update(['low_balance_notified' => false]);
            }

            return $topup;
        });
    }

    public function failTopup(string $topupId, string $reason, ?array $metadata = null): WalletTopup
    {
        $topup = WalletTopup::findOrFail($topupId);
        $topup->fail($reason, $metadata);
        return $topup;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-004: Create Ledger Entry (Immutable)
    // ═══════════════════════════════════════════════════════════

    private function createLedgerEntry(BillingWallet $wallet, array $data): WalletLedgerEntry
    {
        // Get next sequence with lock to prevent race conditions
        $lastSeq = WalletLedgerEntry::where('wallet_id', $wallet->id)
            ->lockForUpdate()
            ->max('sequence') ?? 0;

        // Calculate running balance
        $change = $data['direction'] === 'credit' ? (float) $data['amount'] : -((float) $data['amount']);
        $lastBalance = WalletLedgerEntry::where('wallet_id', $wallet->id)
            ->lockForUpdate()
            ->orderByDesc('sequence')->value('running_balance') ?? 0;
        $runningBalance = (float) $lastBalance + $change;

        $createdAt = now();
        $payload = array_merge($data, [
            'id'              => (string) Str::uuid(),
            'wallet_id'       => $wallet->id,
            'sequence'        => $lastSeq + 1,
            'correlation_id'  => $data['correlation_id'] ?? 'BW-' . Str::uuid(),
            'running_balance' => $runningBalance,
            'created_at'      => $createdAt->format('Y-m-d H:i:s'),
        ]);

        if (array_key_exists('metadata', $payload) && is_array($payload['metadata'])) {
            $payload['metadata'] = json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Direct insert to bypass immutability guard for new entries without
        // serializing timestamps to ISO-8601, which MySQL rejects for this table.
        DB::table('wallet_ledger_entries')->insert($payload);

        return WalletLedgerEntry::where('wallet_id', $wallet->id)->where('sequence', $lastSeq + 1)->first();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-004: Reversal (correction via reverse entry)
    // ═══════════════════════════════════════════════════════════

    public function createReversal(string $walletId, string $originalEntryId, string $reason, ?string $createdBy = null): WalletLedgerEntry
    {
        return DB::transaction(function () use ($walletId, $originalEntryId, $reason, $createdBy) {
            $wallet = BillingWallet::lockForUpdate()->findOrFail($walletId);
            $original = WalletLedgerEntry::where('wallet_id', $walletId)->where('id', $originalEntryId)->firstOrFail();

            // Reverse direction
            $reverseDir = $original->direction === 'credit' ? 'debit' : 'credit';
            $balanceChange = $reverseDir === 'credit' ? (float) $original->amount : -((float) $original->amount);

            $wallet->increment('available_balance', $balanceChange);

            return $this->createLedgerEntry($wallet, [
                'transaction_type' => 'reversal',
                'direction'        => $reverseDir,
                'amount'           => (float) $original->amount,
                'reference_type'   => $original->reference_type,
                'reference_id'     => $original->reference_id,
                'reversal_of'      => $original->id,
                'created_by'       => $createdBy,
                'notes'            => 'Reversal: ' . $reason,
            ]);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-005: Running Balance & Statements
    // ═══════════════════════════════════════════════════════════

    public function getStatement(string $walletId, array $filters = [], int $perPage = 50)
    {
        $query = WalletLedgerEntry::where('wallet_id', $walletId);

        if (!empty($filters['from'])) $query->where('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->where('created_at', '<=', $filters['to']);
        if (!empty($filters['type'])) $query->where('transaction_type', $filters['type']);
        if (!empty($filters['direction'])) $query->where('direction', $filters['direction']);

        return $query->orderByDesc('sequence')->paginate($perPage);
    }

    public function getBalance(string $walletId): array
    {
        $wallet = BillingWallet::findOrFail($walletId);
        return [
            'available_balance'  => (float) $wallet->available_balance,
            'reserved_balance'   => (float) $wallet->reserved_balance,
            'effective_balance'  => $wallet->getEffectiveBalance(),
            'total_credited'     => (float) $wallet->total_credited,
            'total_debited'      => (float) $wallet->total_debited,
            'currency'           => $wallet->currency,
            'is_low_balance'     => $wallet->isLowBalance(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-006: Refunds
    // ═══════════════════════════════════════════════════════════

    public function processRefund(string $walletId, string $shipmentId, float $amount, string $reason, array $options = []): WalletRefund
    {
        return DB::transaction(function () use ($walletId, $shipmentId, $amount, $reason, $options) {
            $wallet = BillingWallet::lockForUpdate()->findOrFail($walletId);

            // Idempotency
            $idempKey = $options['idempotency_key'] ?? "refund-{$shipmentId}";
            $existing = WalletRefund::where('idempotency_key', $idempKey)->first();
            if ($existing) return $existing;

            // Validate: no over-refund
            $totalRefunded = WalletRefund::where('wallet_id', $walletId)
                ->where('shipment_id', $shipmentId)
                ->where('status', 'processed')
                ->sum('amount');

            // Find original debit
            $originalDebit = WalletLedgerEntry::where('wallet_id', $walletId)
                ->where('reference_type', 'shipment')
                ->where('reference_id', $shipmentId)
                ->where('direction', 'debit')
                ->first();

            $maxRefundable = $originalDebit ? (float) $originalDebit->amount : PHP_FLOAT_MAX;
            if (($totalRefunded + $amount) > $maxRefundable) {
                throw new \RuntimeException('ERR_REFUND_EXCEEDS_DEBIT: Refund exceeds original charge');
            }

            // Create refund record
            $refund = WalletRefund::create([
                'wallet_id'        => $walletId,
                'amount'           => $amount,
                'shipment_id'      => $shipmentId,
                'reason'           => $reason,
                'initiated_by_type' => $options['initiated_by_type'] ?? 'system',
                'initiated_by_id'  => $options['initiated_by_id'] ?? null,
                'original_debit_id' => $originalDebit?->id,
                'idempotency_key'  => $idempKey,
                'status'           => 'processed',
            ]);

            // Credit wallet
            $wallet->increment('available_balance', $amount);
            $wallet->increment('total_credited', $amount);

            // Ledger entry
            $this->createLedgerEntry($wallet, [
                'transaction_type' => 'refund',
                'direction'        => 'credit',
                'amount'           => $amount,
                'reference_type'   => 'refund',
                'reference_id'     => $refund->id,
                'notes'            => "Refund for shipment {$shipmentId}: {$reason}",
                'created_by'       => $options['initiated_by_id'] ?? 'system',
            ]);

            return $refund;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-007: Hold / Reservation
    // ═══════════════════════════════════════════════════════════

    public function createHold(
        string $walletId,
        string $shipmentId,
        float $amount,
        ?string $idempotencyKey = null,
        array $attributes = []
    ): WalletHold
    {
        return DB::transaction(function () use ($walletId, $shipmentId, $amount, $idempotencyKey, $attributes) {
            $wallet = BillingWallet::lockForUpdate()->findOrFail($walletId);

            if (! $wallet->isActive()) {
                throw new \RuntimeException('ERR_WALLET_FROZEN');
            }

            // Idempotency
            $key = $idempotencyKey ?? "hold-{$shipmentId}";
            $existing = WalletHold::where('idempotency_key', $key)->first();
            if ($existing) return $existing;

            // Check no active hold exists for this shipment
            if (WalletHold::where('wallet_id', $walletId)->where('shipment_id', $shipmentId)->active()->exists()) {
                throw new \RuntimeException('ERR_HOLD_ALREADY_EXISTS');
            }

            if (!$wallet->hasSufficientFunds($amount)) {
                throw new \RuntimeException('ERR_INSUFFICIENT_BALANCE');
            }

            // Reserve
            $wallet->increment('reserved_balance', $amount);

            $payload = [
                'wallet_id'       => $walletId,
                'amount'          => $amount,
                'shipment_id'     => $shipmentId,
                'status'          => WalletHold::STATUS_ACTIVE,
                'idempotency_key' => $key,
                'expires_at'      => now()->addHours(24),
            ];

            if (Schema::hasColumn('wallet_holds', 'account_id')) {
                $payload['account_id'] = $attributes['account_id'] ?? (string) $wallet->account_id;
            }
            if (Schema::hasColumn('wallet_holds', 'currency')) {
                $payload['currency'] = $attributes['currency'] ?? (string) $wallet->currency;
            }
            if (Schema::hasColumn('wallet_holds', 'source')) {
                $payload['source'] = $attributes['source'] ?? null;
            }
            if (Schema::hasColumn('wallet_holds', 'correlation_id')) {
                $payload['correlation_id'] = $attributes['correlation_id'] ?? null;
            }
            if (Schema::hasColumn('wallet_holds', 'actor_id')) {
                $payload['actor_id'] = $attributes['actor_id'] ?? null;
            }

            $hold = WalletHold::create($payload);

            $this->createLedgerEntry($wallet, [
                'transaction_type' => 'hold',
                'direction'        => 'debit',
                'amount'           => $amount,
                'reference_type'   => 'hold',
                'reference_id'     => $hold->id,
                'notes'            => "Hold for shipment {$shipmentId}",
            ]);

            // FR-BW-008: Check low balance
            $this->checkLowBalance($wallet->fresh());

            return $hold;
        });
    }

    public function captureHold(string $holdId): WalletHold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = WalletHold::lockForUpdate()->findOrFail($holdId);
            if (!$hold->isActive()) throw new \RuntimeException('ERR_HOLD_CAPTURE_FAILED: Hold is not active');

            $wallet = BillingWallet::lockForUpdate()->findOrFail($hold->wallet_id);

            // Convert hold to actual debit
            $wallet->decrement('reserved_balance', (float) $hold->amount);
            $wallet->decrement('available_balance', (float) $hold->amount);
            $wallet->increment('total_debited', (float) $hold->amount);

            $hold->capture();

            $this->createLedgerEntry($wallet, [
                'transaction_type' => 'hold_capture',
                'direction'        => 'debit',
                'amount'           => (float) $hold->amount,
                'reference_type'   => 'shipment',
                'reference_id'     => $hold->shipment_id,
                'notes'            => "Hold captured for shipment {$hold->shipment_id}",
            ]);

            $this->checkLowBalance($wallet->fresh());

            return $hold;
        });
    }

    public function releaseHold(string $holdId): WalletHold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = WalletHold::lockForUpdate()->findOrFail($holdId);
            if (!$hold->isActive()) throw new \RuntimeException('Hold is not active');

            $wallet = BillingWallet::lockForUpdate()->findOrFail($hold->wallet_id);
            $wallet->decrement('reserved_balance', (float) $hold->amount);

            $hold->release();

            $this->createLedgerEntry($wallet, [
                'transaction_type' => 'hold_release',
                'direction'        => 'credit',
                'amount'           => (float) $hold->amount,
                'reference_type'   => 'hold',
                'reference_id'     => $hold->id,
                'notes'            => "Hold released for shipment {$hold->shipment_id}",
            ]);

            return $hold;
        });
    }

    // Direct debit (no hold)
    public function chargeForShipment(string $walletId, string $shipmentId, float $amount, ?string $createdBy = null): WalletLedgerEntry
    {
        return DB::transaction(function () use ($walletId, $shipmentId, $amount, $createdBy) {
            $wallet = BillingWallet::lockForUpdate()->findOrFail($walletId);

            if (!$wallet->hasSufficientFunds($amount)) {
                throw new \RuntimeException('ERR_INSUFFICIENT_BALANCE');
            }

            $wallet->decrement('available_balance', $amount);
            $wallet->increment('total_debited', $amount);

            $entry = $this->createLedgerEntry($wallet, [
                'transaction_type' => 'debit',
                'direction'        => 'debit',
                'amount'           => $amount,
                'reference_type'   => 'shipment',
                'reference_id'     => $shipmentId,
                'created_by'       => $createdBy,
                'notes'            => "Charge for shipment {$shipmentId}",
            ]);

            $this->checkLowBalance($wallet->fresh());

            return $entry;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-008: Low Balance Alert
    // ═══════════════════════════════════════════════════════════

    public function setThreshold(string $walletId, float $threshold): BillingWallet
    {
        $wallet = BillingWallet::findOrFail($walletId);
        $wallet->update(['low_balance_threshold' => $threshold, 'low_balance_notified' => false]);
        return $wallet->fresh();
    }

    public function configureAutoTopup(string $walletId, bool $enabled, ?float $amount = null, ?float $trigger = null): BillingWallet
    {
        $wallet = BillingWallet::findOrFail($walletId);
        $wallet->update([
            'auto_topup_enabled' => $enabled,
            'auto_topup_amount'  => $amount,
            'auto_topup_trigger' => $trigger,
        ]);
        return $wallet->fresh();
    }

    private function checkLowBalance(BillingWallet $wallet): void
    {
        if (!$wallet->isLowBalance()) return;
        if ($wallet->low_balance_notified) return; // Rate-limit

        $wallet->update(['low_balance_notified' => true, 'low_balance_notified_at' => now()]);
        // In production: dispatch LowBalanceEvent → NTF module
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-009: Wallet summary (masked for non-finance roles)
    // ═══════════════════════════════════════════════════════════

    public function getWalletSummary(string $walletId, bool $showDetails = false): array
    {
        $wallet = BillingWallet::findOrFail($walletId);

        if ($showDetails) {
            return [
                'wallet_id'          => $wallet->id,
                'available_balance'  => (float) $wallet->available_balance,
                'reserved_balance'   => (float) $wallet->reserved_balance,
                'effective_balance'  => $wallet->getEffectiveBalance(),
                'total_credited'     => (float) $wallet->total_credited,
                'total_debited'      => (float) $wallet->total_debited,
                'currency'           => $wallet->currency,
                'status'             => $wallet->status,
            ];
        }

        // FR-BW-009: Masked view
        return [
            'wallet_id' => $wallet->id,
            'currency'  => $wallet->currency,
            'status'    => $wallet->status,
            'has_funds' => $wallet->available_balance > 0,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BW-010: Reconciliation
    // ═══════════════════════════════════════════════════════════

    public function runReconciliation(string $date, string $gateway): ReconciliationReport
    {
        $topups = WalletTopup::where('payment_gateway', $gateway)
            ->whereDate('created_at', $date)
            ->get();

        $successTopups = $topups->where('status', 'success');
        $ledgerEntries = WalletLedgerEntry::where('transaction_type', 'topup')
            ->whereDate('created_at', $date)
            ->get();

        $ledgerRefIds = $ledgerEntries->pluck('reference_id')->toArray();
        $unmatchedGateway = $successTopups->filter(fn($t) => !in_array($t->id, $ledgerRefIds))->count();

        $topupIds = $successTopups->pluck('id')->toArray();
        $unmatchedLedger = $ledgerEntries->filter(fn($e) => !in_array($e->reference_id, $topupIds))->count();

        $discrepancy = (float) $successTopups->sum('amount') - (float) $ledgerEntries->where('direction', 'credit')->sum('amount');

        $anomalies = [];
        if ($unmatchedGateway > 0) $anomalies[] = ['type' => 'gateway_without_ledger', 'count' => $unmatchedGateway];
        if ($unmatchedLedger > 0) $anomalies[] = ['type' => 'ledger_without_gateway', 'count' => $unmatchedLedger];
        if (abs($discrepancy) > 0.01) $anomalies[] = ['type' => 'amount_discrepancy', 'amount' => $discrepancy];

        return ReconciliationReport::create([
            'report_date'        => $date,
            'payment_gateway'    => $gateway,
            'total_topups'       => $topups->count(),
            'matched'            => $successTopups->count() - $unmatchedGateway,
            'unmatched_gateway'  => $unmatchedGateway,
            'unmatched_ledger'   => $unmatchedLedger,
            'total_amount'       => (float) $successTopups->sum('amount'),
            'discrepancy_amount' => $discrepancy,
            'anomalies'          => $anomalies ?: null,
            'status'             => 'completed',
        ]);
    }

    public function listReconciliationReports(int $perPage = 20)
    {
        return ReconciliationReport::orderByDesc('report_date')->paginate($perPage);
    }
}
