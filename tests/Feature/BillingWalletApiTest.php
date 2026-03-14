<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletHold;
use App\Services\BillingWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — BW Module (FR-BW-001→010)
 * 22 tests covering all billing & wallet endpoints.
 */
class BillingWalletApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // FR-BW-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_wallet(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/billing/wallets', ['currency' => 'SAR']);
        $r->assertStatus(201)->assertJsonPath('data.currency', 'SAR');
    }

    // FR-BW-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_wallet(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->getJson("/api/v1/billing/wallets/{$w->id}");
        $r->assertOk()->assertJsonPath('data.id', $w->id);
    }

    // FR-BW-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_my_wallet(): void
    {
        BillingWallet::factory()->funded(500)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->getJson('/api/v1/billing/my-wallet');
        $r->assertOk();
    }

    // FR-BW-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_balance(): void
    {
        $w = BillingWallet::factory()->funded(999)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->getJson("/api/v1/billing/wallets/{$w->id}/balance");
        $r->assertOk()->assertJsonStructure(['data' => ['available_balance']]);
    }

    // FR-BW-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_initiate_topup(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/topup", ['amount' => 500]);
        $r->assertStatus(201)->assertJsonPath('data.status', 'pending');
    }

    // FR-BW-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_confirm_topup(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $topup = $service->initiateTopup($w->id, 300);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/topups/{$topup->id}/confirm", ['payment_reference' => 'PAY-123']);
        $r->assertOk()->assertJsonPath('data.status', 'success');
    }

    // FR-BW-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_fail_topup(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $topup = $service->initiateTopup($w->id, 300);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/topups/{$topup->id}/fail", ['reason' => 'Card declined']);
        $r->assertOk()->assertJsonPath('data.status', 'failed');
    }

    // FR-BW-003
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_charge(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/charge", [
            'shipment_id' => 'SH-API-001', 'amount' => 150,
        ]);
        $r->assertOk();
    }

    // FR-BW-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_reversal(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $entry = $service->chargeForShipment($w->id, 'SH-REV', 200);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/reversal", [
            'original_entry_id' => $entry->id, 'reason' => 'Error',
        ]);
        $r->assertOk();
    }

    // FR-BW-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_statement(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $service->chargeForShipment($w->id, 'SH-STM-1', 100);

        $r = $this->actingAs($this->user)->getJson("/api/v1/billing/wallets/{$w->id}/statement");
        $r->assertOk();
    }

    // FR-BW-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_refund(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $service->chargeForShipment($w->id, 'SH-REF-1', 200);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/refund", [
            'shipment_id' => 'SH-REF-1', 'amount' => 200, 'reason' => 'Cancelled',
        ]);
        $r->assertOk();
    }

    // FR-BW-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_hold(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/hold", [
            'shipment_id' => 'SH-HOLD-1', 'amount' => 300,
        ]);
        $r->assertStatus(201)->assertJsonPath('data.status', 'active');
    }

    // FR-BW-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_capture_hold(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $hold = $service->createHold($w->id, 'SH-HOLD-2', 300);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/holds/{$hold->id}/capture");
        $r->assertOk()->assertJsonPath('data.status', 'captured');
    }

    // FR-BW-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_release_hold(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $service = $this->app->make(BillingWalletService::class);
        $hold = $service->createHold($w->id, 'SH-HOLD-3', 300);

        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/holds/{$hold->id}/release");
        $r->assertOk()->assertJsonPath('data.status', 'released');
    }

    // FR-BW-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_threshold(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->putJson("/api/v1/billing/wallets/{$w->id}/threshold", ['threshold' => 200]);
        $r->assertOk();
    }

    // FR-BW-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_configure_auto_topup(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->putJson("/api/v1/billing/wallets/{$w->id}/auto-topup", [
            'enabled' => true, 'amount' => 500, 'trigger' => 100,
        ]);
        $r->assertOk();
    }

    // FR-BW-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_wallet_summary(): void
    {
        $w = BillingWallet::factory()->funded(1000)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->getJson("/api/v1/billing/wallets/{$w->id}/summary");
        $r->assertOk()->assertJsonStructure(['data' => ['available_balance']]);
    }

    // FR-BW-010
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_reconciliation(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/billing/reconciliation', [
            'date' => date('Y-m-d'), 'gateway' => 'tap',
        ]);
        $r->assertOk();
    }

    // FR-BW-010
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_reconciliation_reports(): void
    {
        $r = $this->actingAs($this->user)->getJson('/api/v1/billing/reconciliation');
        $r->assertOk();
    }

    // FR-BW-003
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_charge_insufficient(): void
    {
        $w = BillingWallet::factory()->funded(10)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/charge", [
            'shipment_id' => 'SH-INSUF', 'amount' => 1000,
        ]);
        $r->assertStatus(500); // RuntimeException
    }

    // FR-BW-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_hold_insufficient(): void
    {
        $w = BillingWallet::factory()->funded(50)->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/hold", [
            'shipment_id' => 'SH-INSUF-HOLD', 'amount' => 1000,
        ]);
        $r->assertStatus(500);
    }

    // FR-BW-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_topup_validation(): void
    {
        $w = BillingWallet::factory()->create(['account_id' => $this->account->id]);
        $r = $this->actingAs($this->user)->postJson("/api/v1/billing/wallets/{$w->id}/topup", []);
        $r->assertStatus(422);
    }
}
