<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\BalanceAlert;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\PromoCode;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — PAY Module (FR-PAY-001→011)
 *
 * 45 tests covering all 11 functional requirements.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PaymentService::class);

        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $role->id,
        ]);
    }

    private function seedWallet(float $amount): void
    {
        PaymentTransaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->owner->id,
            'type'        => PaymentTransaction::TYPE_WALLET_TOPUP,
            'amount'      => $amount,
            'net_amount'  => $amount,
            'direction'   => 'credit',
            'status'      => PaymentTransaction::STATUS_COMPLETED,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-001: Prepaid Payment (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_charge_shipping_deducts_wallet(): void
    {
        $this->seedWallet(500);

        $tx = $this->service->chargeShipping(
            $this->account, $this->owner, 'ship-001', 100.00, 'idem_ship_001'
        );

        $this->assertEquals(PaymentTransaction::STATUS_CAPTURED, $tx->status);
        $this->assertEquals('debit', $tx->direction);
        $this->assertLessThan(500, $this->service->getWalletBalance($this->account));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_charge_fails_with_insufficient_balance(): void
    {
        $this->seedWallet(10);

        $this->expectException(\App\Exceptions\BusinessException::class);
        $this->service->chargeShipping(
            $this->account, $this->owner, 'ship-002', 100.00, 'idem_ship_002'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_topup_credits_balance(): void
    {
        $gateway = PaymentGateway::factory()->create();

        $tx = $this->service->topUpWallet(
            $this->account, $this->owner, 200.00, $gateway->slug, 'card', 'idem_topup_001'
        );

        $this->assertEquals(PaymentTransaction::STATUS_CAPTURED, $tx->status);
        $this->assertEquals(200.00, $this->service->getWalletBalance($this->account));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_balance_calculation(): void
    {
        $this->seedWallet(1000);

        PaymentTransaction::factory()->debit()->captured()->create([
            'account_id' => $this->account->id,
            'net_amount' => 300.00,
        ]);

        $balance = $this->service->getWalletBalance($this->account);
        $this->assertEquals(700.00, $balance);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_summary(): void
    {
        $this->seedWallet(500);
        $summary = $this->service->getWalletSummary($this->account);

        $this->assertArrayHasKey('balance', $summary);
        $this->assertArrayHasKey('total_credits', $summary);
        $this->assertArrayHasKey('total_debits', $summary);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-002: Idempotent Payment (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotent_charge_returns_same(): void
    {
        $this->seedWallet(500);

        $tx1 = $this->service->chargeShipping($this->account, $this->owner, 'ship-X', 50.00, 'idem_dup_001');
        $tx2 = $this->service->chargeShipping($this->account, $this->owner, 'ship-X', 50.00, 'idem_dup_001');

        $this->assertEquals($tx1->id, $tx2->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_different_keys_create_different_transactions(): void
    {
        $this->seedWallet(1000);

        $tx1 = $this->service->chargeShipping($this->account, $this->owner, 'ship-A', 50.00, 'idem_a');
        $tx2 = $this->service->chargeShipping($this->account, $this->owner, 'ship-B', 50.00, 'idem_b');

        $this->assertNotEquals($tx1->id, $tx2->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotency_key_unique_constraint(): void
    {
        $this->seedWallet(500);

        $gateway = PaymentGateway::factory()->create();
        $tx1 = $this->service->topUpWallet($this->account, $this->owner, 100, $gateway->slug, 'card', 'idem_topup_dup');
        $tx2 = $this->service->topUpWallet($this->account, $this->owner, 100, $gateway->slug, 'card', 'idem_topup_dup');

        $this->assertEquals($tx1->id, $tx2->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-003: Subscription Management (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_subscribe_creates_active_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 99]);
        $this->seedWallet(5000);

        $sub = $this->service->subscribe($this->account, $this->owner, $plan->id, 'monthly', 'idem_sub_001');

        $this->assertEquals(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertTrue($sub->isActive());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cannot_subscribe_twice(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $this->seedWallet(10000);

        $this->service->subscribe($this->account, $this->owner, $plan->id, 'monthly', 'idem_sub_2a');

        $this->expectException(\App\Exceptions\BusinessException::class);
        $this->service->subscribe($this->account, $this->owner, $plan->id, 'monthly', 'idem_sub_2b');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $this->seedWallet(5000);
        $this->service->subscribe($this->account, $this->owner, $plan->id, 'monthly', 'idem_sub_cancel');

        $sub = $this->service->cancelSubscription($this->account);
        $this->assertEquals(Subscription::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_subscription_days_remaining(): void
    {
        $sub = Subscription::factory()->create([
            'account_id' => $this->account->id,
            'expires_at' => now()->addDays(15),
        ]);

        $this->assertGreaterThanOrEqual(14, $sub->daysRemaining());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_expired_subscription(): void
    {
        $sub = Subscription::factory()->expired()->create(['account_id' => $this->account->id]);
        $this->assertTrue($sub->isExpired());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_plans(): void
    {
        SubscriptionPlan::factory()->count(3)->create();
        $plans = $this->service->listPlans();
        $this->assertCount(3, $plans);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-004: Payment Gateway (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_gateway_fee_calculation(): void
    {
        $gw = PaymentGateway::factory()->create(['transaction_fee_pct' => 2.5, 'transaction_fee_fixed' => 1.00]);
        $fee = $gw->calculateFee(100);
        $this->assertEquals(3.50, $fee);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_gateway_currency_support(): void
    {
        $gw = PaymentGateway::factory()->create(['supported_currencies' => ['SAR', 'USD']]);
        $this->assertTrue($gw->supportsCurrency('SAR'));
        $this->assertFalse($gw->supportsCurrency('JPY'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_active_gateways(): void
    {
        PaymentGateway::factory()->count(2)->create();
        PaymentGateway::factory()->create(['is_active' => false]);

        $gateways = $this->service->listGateways();
        $this->assertCount(2, $gateways);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-005: Invoice Generation (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invoice_generated_on_charge(): void
    {
        $this->seedWallet(500);
        $this->service->chargeShipping($this->account, $this->owner, 'ship-inv', 100, 'idem_inv_001');

        $this->assertDatabaseHas('invoices', ['account_id' => $this->account->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invoice_number_unique(): void
    {
        $num1 = Invoice::generateNumber();
        Invoice::factory()->create(['invoice_number' => $num1]);
        $num2 = Invoice::generateNumber();

        $this->assertNotEquals($num1, $num2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_invoices(): void
    {
        Invoice::factory()->count(3)->create(['account_id' => $this->account->id]);
        $invoices = $this->service->listInvoices($this->account);
        $this->assertEquals(3, $invoices->total());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-006: Tax Calculation (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_vat_15_percent(): void
    {
        $tax = $this->service->calculateTax(100);
        $this->assertEquals(15.00, $tax);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_tax_rounding(): void
    {
        $tax = $this->service->calculateTax(33.33);
        $this->assertEquals(5.00, $tax); // 33.33 * 0.15 = 4.9995 → 5.00
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_shipping_charge_includes_tax(): void
    {
        $this->seedWallet(500);
        $tx = $this->service->chargeShipping($this->account, $this->owner, 'ship-tax', 100, 'idem_tax_001');

        $this->assertGreaterThan(0, $tx->tax_amount);
        $this->assertEquals($tx->amount - $tx->discount_amount + $tx->tax_amount, $tx->net_amount);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-007: Promo Codes (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_valid_promo_code(): void
    {
        $promo = PromoCode::factory()->create(['code' => 'SAVE10']);
        $result = $this->service->validatePromoCode('SAVE10', $this->account->id, 200, 'shipping');
        $this->assertTrue($result['valid']);
        $this->assertGreaterThan(0, $result['discount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_expired_promo_code(): void
    {
        PromoCode::factory()->expired()->create(['code' => 'OLDCODE']);
        $result = $this->service->validatePromoCode('OLDCODE', $this->account->id, 100);
        $this->assertFalse($result['valid']);
        $this->assertEquals('ERR_PROMO_EXPIRED', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_promo_percentage_discount(): void
    {
        $promo = PromoCode::factory()->create(['discount_type' => 'percentage', 'discount_value' => 20]);
        $discount = $promo->calculateDiscount(200);
        $this->assertEquals(40.00, $discount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_promo_fixed_discount(): void
    {
        $promo = PromoCode::factory()->fixed()->create(['discount_value' => 25]);
        $discount = $promo->calculateDiscount(200);
        $this->assertEquals(25.00, $discount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_promo_max_discount_cap(): void
    {
        $promo = PromoCode::factory()->create([
            'discount_type' => 'percentage', 'discount_value' => 50, 'max_discount_amount' => 30,
        ]);
        $discount = $promo->calculateDiscount(200);
        $this->assertEquals(30.00, $discount); // 50% = 100, capped at 30
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-008: Transaction Log (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transaction_log_paginated(): void
    {
        PaymentTransaction::factory()->count(5)->create(['account_id' => $this->account->id]);
        $txs = $this->service->getTransactions($this->account, [], 3);
        $this->assertEquals(5, $txs->total());
        $this->assertCount(3, $txs->items());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transaction_log_filtered(): void
    {
        PaymentTransaction::factory()->count(2)->create(['account_id' => $this->account->id, 'type' => 'wallet_topup']);
        PaymentTransaction::factory()->debit()->captured()->create(['account_id' => $this->account->id]);

        $txs = $this->service->getTransactions($this->account, ['type' => 'wallet_topup']);
        $this->assertEquals(2, $txs->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transaction_status_checks(): void
    {
        $tx = PaymentTransaction::factory()->make(['status' => PaymentTransaction::STATUS_CAPTURED, 'direction' => 'debit']);
        $this->assertTrue($tx->isSuccessful());
        $this->assertTrue($tx->canRefund());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-009: Subscription Status for Pricing (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_active_subscription_status(): void
    {
        Subscription::factory()->create([
            'account_id' => $this->account->id,
            'plan_id'    => SubscriptionPlan::factory()->create()->id,
        ]);

        $status = $this->service->getSubscriptionStatus($this->account);
        $this->assertEquals('active', $status['status']);
        $this->assertLessThanOrEqual(1.0, $status['markup_multiplier']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_expired_subscription_higher_markup(): void
    {
        Subscription::factory()->expired()->create([
            'account_id' => $this->account->id,
            'plan_id'    => SubscriptionPlan::factory()->create()->id,
        ]);

        $status = $this->service->getSubscriptionStatus($this->account);
        $this->assertEquals('expired', $status['status']);
        $this->assertEquals(1.30, $status['markup_multiplier']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-010: Refunds (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_full_refund(): void
    {
        $this->seedWallet(500);
        $charge = $this->service->chargeShipping($this->account, $this->owner, 'ship-ref', 100, 'idem_ref_orig');

        $refund = $this->service->refund($charge->id, $this->owner, $charge->net_amount, 'Customer request', 'idem_ref_001');

        $this->assertEquals(PaymentTransaction::TYPE_REFUND, $refund->type);
        $this->assertEquals('credit', $refund->direction);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_partial_refund(): void
    {
        $this->seedWallet(500);
        $charge = $this->service->chargeShipping($this->account, $this->owner, 'ship-pref', 100, 'idem_pref_orig');

        $this->service->refund($charge->id, $this->owner, 30.00, 'Partial refund', 'idem_pref_001');

        $this->assertEquals(PaymentTransaction::STATUS_PARTIALLY_REFUNDED, $charge->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refund_exceeds_throws(): void
    {
        $this->seedWallet(500);
        $charge = $this->service->chargeShipping($this->account, $this->owner, 'ship-exc', 50, 'idem_exc_orig');

        $this->expectException(\App\Exceptions\BusinessException::class);
        $this->service->refund($charge->id, $this->owner, 99999.00, 'Too much', 'idem_exc_ref');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refund_idempotent(): void
    {
        $this->seedWallet(500);
        $charge = $this->service->chargeShipping($this->account, $this->owner, 'ship-rid', 100, 'idem_rid_orig');

        $r1 = $this->service->refund($charge->id, $this->owner, 50, 'Dup refund', 'idem_rid_ref');
        $r2 = $this->service->refund($charge->id, $this->owner, 50, 'Dup refund', 'idem_rid_ref');

        $this->assertEquals($r1->id, $r2->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-PAY-011: Balance Alerts (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_set_balance_alert(): void
    {
        $alert = $this->service->setBalanceAlert($this->account, $this->owner, 50.00);
        $this->assertEquals(50.00, $alert->threshold_amount);
        $this->assertTrue($alert->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_alert_trigger_check(): void
    {
        $alert = BalanceAlert::create([
            'account_id'       => $this->account->id,
            'user_id'          => $this->owner->id,
            'threshold_amount' => 100,
            'is_active'        => true,
            'channels'         => ['email'],
        ]);

        $this->assertTrue($alert->shouldTrigger(50));
        $this->assertFalse($alert->shouldTrigger(150));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_balance_alerts(): void
    {
        $this->service->setBalanceAlert($this->account, $this->owner, 20);
        $alerts = $this->service->getBalanceAlerts($this->account);
        $this->assertCount(1, $alerts);
    }
}
