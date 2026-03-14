<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\PromoCode;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API Tests — PAY Module (FR-PAY-001→011)
 *
 * 18 tests covering all payment API endpoints.
 */
class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
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
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'net_amount'  => $amount,
            'direction'   => 'credit',
            'status'      => PaymentTransaction::STATUS_COMPLETED,
        ]);
    }

    // ═══════════════ FR-PAY-001: Top-up ══════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_topup_wallet(): void
    {
        $gateway = PaymentGateway::factory()->create();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/topup', [
                'amount'          => 500,
                'gateway'         => $gateway->slug,
                'payment_method'  => 'card',
                'idempotency_key' => 'api_topup_' . Str::random(10),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'captured');
    }

    // ═══════════════ FR-PAY-001/002: Charge Shipping ═════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_charge_shipping(): void
    {
        $this->seedWallet(1000);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/charge-shipping', [
                'shipment_id'     => 'ship-api-001',
                'amount'          => 50.00,
                'idempotency_key' => 'api_charge_' . Str::random(10),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.type', 'shipping_charge');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_charge_insufficient_balance(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/charge-shipping', [
                'shipment_id'     => 'ship-api-fail',
                'amount'          => 999999,
                'idempotency_key' => 'api_fail_' . Str::random(10),
            ]);

        $response->assertStatus(500); // BusinessException
    }

    // ═══════════════ FR-PAY-008: Wallet & Transactions ═══════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_wallet_summary(): void
    {
        $this->seedWallet(300);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/payments/wallet');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['balance', 'currency', 'total_credits']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_transactions(): void
    {
        PaymentTransaction::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/payments/transactions');

        $response->assertOk()->assertJsonPath('data.total', 3);
    }

    // ═══════════════ FR-PAY-010: Refund ══════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_refund(): void
    {
        $this->seedWallet(500);
        $charge = PaymentTransaction::factory()->debit()->captured()->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'net_amount' => 100,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/refund', [
                'transaction_id'  => $charge->id,
                'amount'          => 50,
                'reason'          => 'Test refund',
                'idempotency_key' => 'api_refund_' . Str::random(10),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.type', 'refund');
    }

    // ═══════════════ FR-PAY-003: Subscriptions ═══════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_subscribe(): void
    {
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 99]);
        $this->seedWallet(5000);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/subscriptions/subscribe', [
                'plan_id'         => $plan->id,
                'billing_cycle'   => 'monthly',
                'idempotency_key' => 'api_sub_' . Str::random(10),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'active');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_cancel_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->create(['account_id' => $this->account->id, 'plan_id' => $plan->id]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/subscriptions/cancel');

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_subscription_status(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/subscriptions/status');

        $response->assertOk()->assertJsonStructure(['data' => ['status']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_plans(): void
    {
        SubscriptionPlan::factory()->count(2)->create();

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/subscriptions/plans');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // ═══════════════ FR-PAY-005: Invoices ════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_invoices(): void
    {
        Invoice::factory()->count(2)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/payments/invoices');

        $response->assertOk()->assertJsonPath('data.total', 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_invoice(): void
    {
        $inv = Invoice::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/payments/invoices/{$inv->id}");

        $response->assertOk();
    }

    // ═══════════════ FR-PAY-007: Promo Codes ═════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_validate_promo(): void
    {
        PromoCode::factory()->create(['code' => 'API10']);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/promo/validate', [
                'code'   => 'API10',
                'amount' => 200,
            ]);

        $response->assertOk()->assertJsonPath('data.valid', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_promo(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/promo', [
                'code'           => 'NEWPROMO',
                'discount_type'  => 'percentage',
                'discount_value' => 15,
                'expires_at'     => now()->addMonth()->toIso8601String(),
            ]);

        $response->assertStatus(201);
    }

    // ═══════════════ FR-PAY-004: Gateways ════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_gateways(): void
    {
        PaymentGateway::factory()->count(2)->create();

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/payments/gateways');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // ═══════════════ FR-PAY-011: Balance Alerts ══════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_balance_alert(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/payments/balance-alerts', [
                'threshold_amount' => 50,
                'channels'         => ['email', 'in_app'],
            ]);

        $response->assertStatus(201);
    }

    // ═══════════════ FR-PAY-006: Tax Calculator ══════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_tax_calculator(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/payments/tax-calculate?amount=100');

        $response->assertOk()
            ->assertJsonPath('data.tax_rate', 15.00)
            ->assertJsonPath('data.tax_amount', 15.00)
            ->assertJsonPath('data.total', 115.00);
    }
}
