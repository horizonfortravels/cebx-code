<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\BalanceAlert;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PaymentService — FR-PAY-001→011 (11 requirements)
 *
 * FR-PAY-001: Prepaid payment before label issuance
 * FR-PAY-002: Idempotent payment execution
 * FR-PAY-003: Subscription & plan management
 * FR-PAY-004: Payment gateway integration
 * FR-PAY-005: Invoice & receipt generation
 * FR-PAY-006: Tax calculation (Saudi VAT 15%)
 * FR-PAY-007: Promo codes & discounts
 * FR-PAY-008: Transaction & wallet log
 * FR-PAY-009: Subscription status for pricing
 * FR-PAY-010: Refunds
 * FR-PAY-011: Balance & payment notifications
 */
class PaymentService
{
    private float $vatRate = 15.00;

    public function __construct(
        private AuditService $audit,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-001: Prepaid Payment (charge before label)
    // FR-PAY-002: Idempotent Execution
    // ═══════════════════════════════════════════════════════════

    /**
     * Charge shipping cost from wallet. Must succeed before label issuance.
     * Uses idempotency_key to prevent duplicate charges.
     */
    public function chargeShipping(
        Account $account,
        User $user,
        string $shipmentId,
        float $amount,
        string $idempotencyKey,
        ?string $promoCode = null
    ): PaymentTransaction {
        // FR-PAY-002: Idempotency check
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing; // Return existing, no duplicate
        }

        return DB::transaction(function () use ($account, $user, $shipmentId, $amount, $idempotencyKey, $promoCode) {
            $discount = 0;
            $promoCodeId = null;

            // FR-PAY-007: Apply promo code
            if ($promoCode) {
                $promo = PromoCode::where('code', $promoCode)->first();
                if ($promo) {
                    $validation = $promo->validate($account->id, $amount, 'shipping');
                    if ($validation['valid']) {
                        $discount = $validation['discount'];
                        $promoCodeId = $promo->id;
                    }
                }
            }

            // FR-PAY-006: Tax calculation
            $subtotal = $amount - $discount;
            $tax = $this->calculateTax($subtotal);
            $netAmount = $subtotal + $tax;

            // Check wallet balance
            $balance = $this->getWalletBalance($account);
            if ($balance < $netAmount) {
                throw new BusinessException('Insufficient wallet balance', 'ERR_INSUFFICIENT_BALANCE');
            }

            $transaction = PaymentTransaction::create([
                'account_id'      => $account->id,
                'user_id'         => $user->id,
                'idempotency_key' => $idempotencyKey,
                'type'            => PaymentTransaction::TYPE_SHIPPING_CHARGE,
                'entity_type'     => 'shipment',
                'entity_id'       => $shipmentId,
                'amount'          => $amount,
                'tax_amount'      => $tax,
                'discount_amount' => $discount,
                'net_amount'      => $netAmount,
                'currency'        => $account->currency ?? 'SAR',
                'direction'       => 'debit',
                'balance_before'  => $balance,
                'balance_after'   => $balance - $netAmount,
                'status'          => PaymentTransaction::STATUS_CAPTURED,
                'gateway'         => 'wallet',
                'payment_method'  => 'wallet',
                'promo_code_id'   => $promoCodeId,
            ]);

            // Record promo usage
            if ($promoCodeId) {
                $promo->recordUsage($account->id, $discount, $transaction->id);
            }

            // FR-PAY-005: Auto-generate receipt
            $this->generateInvoice($transaction, $account);

            // FR-PAY-011: Check balance alerts
            $this->checkBalanceAlerts($account, $balance - $netAmount);

            return $transaction;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-001: Wallet Top-up
    // FR-PAY-004: Gateway Integration
    // ═══════════════════════════════════════════════════════════

    /**
     * Top up wallet via payment gateway.
     */
    public function topUpWallet(
        Account $account,
        User $user,
        float $amount,
        string $gatewaySlug,
        string $paymentMethod,
        string $idempotencyKey,
        array $gatewayData = []
    ): PaymentTransaction {
        // FR-PAY-002: Idempotency
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) return $existing;

        $gateway = PaymentGateway::where('slug', $gatewaySlug)->active()->firstOrFail();

        if (!$gateway->supportsCurrency($account->currency ?? 'SAR')) {
            throw new BusinessException('Currency not supported by gateway', 'ERR_CURRENCY_NOT_SUPPORTED');
        }

        return DB::transaction(function () use ($account, $user, $amount, $gateway, $paymentMethod, $idempotencyKey, $gatewayData) {
            $balance = $this->getWalletBalance($account);

            $transaction = PaymentTransaction::create([
                'account_id'      => $account->id,
                'user_id'         => $user->id,
                'idempotency_key' => $idempotencyKey,
                'type'            => PaymentTransaction::TYPE_WALLET_TOPUP,
                'amount'          => $amount,
                'tax_amount'      => 0,
                'discount_amount' => 0,
                'net_amount'      => $amount,
                'currency'        => $account->currency ?? 'SAR',
                'direction'       => 'credit',
                'balance_before'  => $balance,
                'balance_after'   => $balance + $amount,
                'status'          => PaymentTransaction::STATUS_PROCESSING,
                'gateway'         => $gateway->slug,
                'payment_method'  => $paymentMethod,
            ]);

            // FR-PAY-004: Process with gateway (simulated)
            $gatewayResult = $this->processGatewayPayment($gateway, $amount, $gatewayData);

            if ($gatewayResult['success']) {
                $transaction->update([
                    'status'                 => PaymentTransaction::STATUS_CAPTURED,
                    'gateway_transaction_id' => $gatewayResult['transaction_id'],
                    'gateway_response'       => $gatewayResult,
                ]);
            } else {
                $transaction->update([
                    'status'          => PaymentTransaction::STATUS_FAILED,
                    'failure_reason'  => $gatewayResult['error'] ?? 'Payment failed',
                    'gateway_response' => $gatewayResult,
                    'balance_after'   => $balance, // Revert
                ]);
                throw new BusinessException('Payment failed: ' . ($gatewayResult['error'] ?? ''), 'ERR_PAYMENT_FAILED');
            }

            return $transaction;
        });
    }

    /**
     * Simulated gateway processing.
     */
    private function processGatewayPayment(PaymentGateway $gateway, float $amount, array $data): array
    {
        // In production: Stripe::charges()->create(), PayPal SDK, etc.
        return [
            'success'        => true,
            'transaction_id' => 'gw_' . Str::random(20),
            'gateway'        => $gateway->slug,
            'amount'         => $amount,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-003: Subscription Management
    // ═══════════════════════════════════════════════════════════

    public function subscribe(
        Account $account,
        User $user,
        string $planId,
        string $billingCycle,
        string $idempotencyKey,
        ?string $promoCode = null
    ): Subscription {
        $plan = SubscriptionPlan::findOrFail($planId);
        $price = $plan->getPriceForCycle($billingCycle);

        // Check for active subscription
        $active = Subscription::where('account_id', $account->id)->active()->first();
        if ($active) {
            throw new BusinessException('Account already has an active subscription', 'ERR_SUBSCRIPTION_ALREADY_ACTIVE');
        }

        // Apply promo code
        $discount = 0;
        if ($promoCode) {
            $promo = PromoCode::where('code', $promoCode)->first();
            if ($promo) {
                $validation = $promo->validate($account->id, $price, 'subscription');
                if ($validation['valid']) $discount = $validation['discount'];
            }
        }

        $tax = $this->calculateTax($price - $discount);
        $total = ($price - $discount) + $tax;

        // Charge payment
        $transaction = $this->chargeForSubscription($account, $user, $total, $idempotencyKey, $plan);

        $expiresAt = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

        $subscription = Subscription::create([
            'account_id'    => $account->id,
            'plan_id'       => $plan->id,
            'billing_cycle' => $billingCycle,
            'status'        => Subscription::STATUS_ACTIVE,
            'starts_at'     => now(),
            'expires_at'    => $expiresAt,
            'amount_paid'   => $total,
            'currency'      => $account->currency ?? 'SAR',
        ]);

        $this->generateInvoice($transaction, $account, 'subscription');

        return $subscription;
    }

    private function chargeForSubscription(Account $account, User $user, float $amount, string $key, SubscriptionPlan $plan): PaymentTransaction
    {
        $balance = $this->getWalletBalance($account);
        if ($balance < $amount) {
            throw new BusinessException('Insufficient balance for subscription', 'ERR_SUBSCRIPTION_PAYMENT_FAILED');
        }

        return PaymentTransaction::create([
            'account_id'      => $account->id,
            'user_id'         => $user->id,
            'idempotency_key' => $key,
            'type'            => PaymentTransaction::TYPE_SUBSCRIPTION,
            'entity_type'     => 'subscription_plan',
            'entity_id'       => $plan->id,
            'amount'          => $amount,
            'tax_amount'      => $this->calculateTax($amount / (1 + $this->vatRate / 100)),
            'discount_amount' => 0,
            'net_amount'      => $amount,
            'currency'        => $account->currency ?? 'SAR',
            'direction'       => 'debit',
            'balance_before'  => $balance,
            'balance_after'   => $balance - $amount,
            'status'          => PaymentTransaction::STATUS_CAPTURED,
            'gateway'         => 'wallet',
            'payment_method'  => 'wallet',
        ]);
    }

    public function cancelSubscription(Account $account): Subscription
    {
        $sub = Subscription::where('account_id', $account->id)->active()->firstOrFail();
        $sub->cancel();
        return $sub;
    }

    public function renewSubscription(Account $account, User $user, string $idempotencyKey): Subscription
    {
        $sub = Subscription::where('account_id', $account->id)->latest()->firstOrFail();
        $plan = $sub->plan;
        $price = $plan->getPriceForCycle($sub->billing_cycle);
        $tax = $this->calculateTax($price);
        $total = $price + $tax;

        $this->chargeForSubscription($account, $user, $total, $idempotencyKey, $plan);
        $sub->renew();

        return $sub->fresh();
    }

    public function listPlans(): Collection
    {
        return SubscriptionPlan::active()->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-005: Invoice Generation
    // ═══════════════════════════════════════════════════════════

    public function generateInvoice(PaymentTransaction $transaction, Account $account, string $description = null): Invoice
    {
        $invoice = Invoice::create([
            'account_id'      => $account->id,
            'transaction_id'  => $transaction->id,
            'invoice_number'  => Invoice::generateNumber(),
            'type'            => $transaction->direction === 'credit' ? Invoice::TYPE_RECEIPT : Invoice::TYPE_INVOICE,
            'subtotal'        => $transaction->amount - $transaction->discount_amount,
            'tax_amount'      => $transaction->tax_amount,
            'discount_amount' => $transaction->discount_amount,
            'total'           => $transaction->net_amount,
            'currency'        => $transaction->currency,
            'tax_rate'        => $this->vatRate,
            'billing_name'    => $account->company_name ?? $account->name,
            'tax_number'      => $account->tax_number ?? null,
            'status'          => Invoice::STATUS_PAID,
            'issued_at'       => now(),
            'paid_at'         => now(),
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => $description ?? $this->describeTransaction($transaction),
            'quantity'    => 1,
            'unit_price'  => $transaction->amount,
            'tax_amount'  => $transaction->tax_amount,
            'total'       => $transaction->net_amount,
            'entity_type' => $transaction->entity_type,
            'entity_id'   => $transaction->entity_id,
        ]);

        return $invoice;
    }

    private function describeTransaction(PaymentTransaction $t): string
    {
        return match ($t->type) {
            PaymentTransaction::TYPE_SHIPPING_CHARGE => "رسوم شحن - {$t->entity_id}",
            PaymentTransaction::TYPE_SUBSCRIPTION    => "رسوم اشتراك",
            PaymentTransaction::TYPE_WALLET_TOPUP    => "تعبئة رصيد",
            PaymentTransaction::TYPE_REFUND          => "استرداد",
            default => $t->type,
        };
    }

    public function getInvoice(string $invoiceId): Invoice
    {
        return Invoice::with('items')->findOrFail($invoiceId);
    }

    public function listInvoices(Account $account, int $perPage = 20)
    {
        return Invoice::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-006: Tax Calculation
    // ═══════════════════════════════════════════════════════════

    public function calculateTax(float $amount): float
    {
        return round($amount * $this->vatRate / 100, 2);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-007: Promo Code Management
    // ═══════════════════════════════════════════════════════════

    public function validatePromoCode(string $code, string $accountId, float $amount, string $context = 'shipping'): array
    {
        $promo = PromoCode::where('code', strtoupper($code))->first();
        if (!$promo) return ['valid' => false, 'error' => 'ERR_PROMO_NOT_FOUND'];
        return $promo->validate($accountId, $amount, $context);
    }

    public function createPromoCode(array $data): PromoCode
    {
        $data['code'] = strtoupper($data['code']);
        return PromoCode::create($data);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-008: Transaction Log
    // ═══════════════════════════════════════════════════════════

    public function getTransactions(Account $account, array $filters = [], int $perPage = 20)
    {
        $query = PaymentTransaction::where('account_id', $account->id);

        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['from'])) $query->where('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->where('created_at', '<=', $filters['to']);
        if (!empty($filters['direction'])) $query->where('direction', $filters['direction']);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getWalletBalance(Account $account): float
    {
        $credits = PaymentTransaction::where('account_id', $account->id)
            ->where('direction', 'credit')
            ->successful()
            ->sum('net_amount');

        $debits = PaymentTransaction::where('account_id', $account->id)
            ->where('direction', 'debit')
            ->successful()
            ->sum('net_amount');

        return round($credits - $debits, 2);
    }

    public function getWalletSummary(Account $account): array
    {
        return [
            'balance'       => $this->getWalletBalance($account),
            'currency'      => $account->currency ?? 'SAR',
            'total_credits' => PaymentTransaction::where('account_id', $account->id)->where('direction', 'credit')->successful()->sum('net_amount'),
            'total_debits'  => PaymentTransaction::where('account_id', $account->id)->where('direction', 'debit')->successful()->sum('net_amount'),
            'total_refunds' => PaymentTransaction::where('account_id', $account->id)->where('type', 'refund')->successful()->sum('net_amount'),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-009: Subscription Status for Pricing
    // ═══════════════════════════════════════════════════════════

    public function getSubscriptionStatus(Account $account): array
    {
        $sub = Subscription::where('account_id', $account->id)->latest()->first();

        if (!$sub) {
            return ['status' => 'none', 'plan' => null, 'markup_multiplier' => 1.30];
        }

        return [
            'status'             => $sub->isActive() ? 'active' : 'expired',
            'plan'               => $sub->plan->slug ?? null,
            'plan_name'          => $sub->plan->name ?? null,
            'expires_at'         => $sub->expires_at,
            'days_remaining'     => $sub->daysRemaining(),
            'markup_multiplier'  => $sub->isActive() ? $sub->plan->markup_multiplier : 1.30,
            'shipping_discount'  => $sub->isActive() ? $sub->plan->shipping_discount_pct : 0,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-010: Refunds
    // ═══════════════════════════════════════════════════════════

    public function refund(
        string $transactionId,
        User $user,
        float $amount,
        string $reason,
        string $idempotencyKey
    ): PaymentTransaction {
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) return $existing;

        $original = PaymentTransaction::findOrFail($transactionId);

        if (!$original->canRefund()) {
            throw new BusinessException('Transaction cannot be refunded', 'ERR_REFUND_NOT_ALLOWED');
        }

        if ($amount > $original->getRemainingRefundable()) {
            throw new BusinessException('Refund amount exceeds refundable amount', 'ERR_REFUND_EXCEEDS');
        }

        return DB::transaction(function () use ($original, $user, $amount, $reason, $idempotencyKey) {
            $balance = $this->getWalletBalance(Account::find($original->account_id));

            $refund = PaymentTransaction::create([
                'account_id'      => $original->account_id,
                'user_id'         => $user->id,
                'idempotency_key' => $idempotencyKey,
                'type'            => PaymentTransaction::TYPE_REFUND,
                'entity_type'     => $original->entity_type,
                'entity_id'       => $original->entity_id,
                'amount'          => $amount,
                'tax_amount'      => 0,
                'discount_amount' => 0,
                'net_amount'      => $amount,
                'currency'        => $original->currency,
                'direction'       => 'credit',
                'balance_before'  => $balance,
                'balance_after'   => $balance + $amount,
                'status'          => PaymentTransaction::STATUS_COMPLETED,
                'gateway'         => 'wallet',
                'payment_method'  => 'wallet',
                'refund_of_id'    => $original->id,
                'notes'           => $reason,
            ]);

            // Update original status
            $remaining = $original->getRemainingRefundable();
            $original->update([
                'status' => $remaining <= 0
                    ? PaymentTransaction::STATUS_REFUNDED
                    : PaymentTransaction::STATUS_PARTIALLY_REFUNDED,
            ]);

            return $refund;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-011: Balance Alerts
    // ═══════════════════════════════════════════════════════════

    public function setBalanceAlert(Account $account, ?User $user, float $threshold, array $channels = ['email', 'in_app']): BalanceAlert
    {
        return BalanceAlert::updateOrCreate(
            ['account_id' => $account->id, 'user_id' => $user?->id],
            ['threshold_amount' => $threshold, 'channels' => $channels, 'is_active' => true]
        );
    }

    public function checkBalanceAlerts(Account $account, float $balance): void
    {
        $alerts = BalanceAlert::where('account_id', $account->id)
            ->where('is_active', true)
            ->get();

        foreach ($alerts as $alert) {
            if ($alert->shouldTrigger($balance)) {
                $alert->update(['last_triggered_at' => now()]);
                // In production: dispatch NTF notification
            }
        }
    }

    public function getBalanceAlerts(Account $account): Collection
    {
        return BalanceAlert::where('account_id', $account->id)->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-004: Gateway Management
    // ═══════════════════════════════════════════════════════════

    public function listGateways(): Collection
    {
        return PaymentGateway::active()->get();
    }
}
