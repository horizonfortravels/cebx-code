<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\ContentDeclaration;
use App\Models\KycVerification;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletHold;
use App\Models\WaiverVersion;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentWalletPreflightApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WaiverVersion::factory()->english()->create([
            'version' => '2026.03',
            'is_active' => true,
        ]);
    }

    public function test_sufficient_balance_creates_wallet_preflight_reservation(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPreflightActor();
        BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'SAR',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $response = $this->postJson(
            '/api/v1/shipments/' . $shipment->id . '/wallet-preflight',
            ['correlation_id' => 'REQ-PREFLIGHT-001'],
            $this->authHeaders($user)
        )
            ->assertCreated()
            ->assertJsonPath('data.shipment_id', (string) $shipment->id)
            ->assertJsonPath('data.shipment_status', Shipment::STATUS_PAYMENT_PENDING)
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.source', 'shipment_preflight')
            ->assertJsonPath('data.created', true);

        $shipment->refresh();
        $hold = WalletHold::query()->where('shipment_id', (string) $shipment->id)->firstOrFail();

        $this->assertSame(Shipment::STATUS_PAYMENT_PENDING, (string) $shipment->status);
        $this->assertSame((string) $hold->id, (string) $shipment->balance_reservation_id);
        $this->assertSame((string) $user->account_id, (string) $hold->account_id);
        $this->assertSame((string) $user->id, (string) $hold->actor_id);
        $this->assertSame('shipment_preflight', (string) $hold->source);
        $this->assertSame('REQ-PREFLIGHT-001', (string) $hold->correlation_id);
        $this->assertSame(number_format((float) $shipment->reserved_amount, 2, '.', ''), number_format((float) $hold->amount, 2, '.', ''));
        $this->assertSame((string) $hold->id, (string) $response->json('data.reservation_id'));
    }

    public function test_insufficient_balance_blocks_preflight_without_leaking_reservation(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPreflightActor();
        BillingWallet::factory()->funded(10)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'SAR',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->postJson(
            '/api/v1/shipments/' . $shipment->id . '/wallet-preflight',
            [],
            $this->authHeaders($user)
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_INSUFFICIENT_BALANCE');

        $shipment->refresh();
        $wallet = BillingWallet::query()->where('account_id', (string) $user->account_id)->where('currency', 'SAR')->firstOrFail();

        $this->assertNull($shipment->balance_reservation_id);
        $this->assertNull($shipment->reserved_amount);
        $this->assertSame(Shipment::STATUS_DECLARATION_COMPLETE, (string) $shipment->status);
        $this->assertSame(0.0, (float) $wallet->reserved_balance);
        $this->assertSame(0, WalletHold::query()->where('shipment_id', (string) $shipment->id)->count());
    }

    public function test_second_preflight_attempt_does_not_overspend_wallet(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPreflightActor();
        BillingWallet::factory()->funded(30)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'SAR',
        ]);

        $shipmentA = $this->createDeclarationCompleteShipment($user);
        $shipmentB = $this->createDeclarationCompleteShipment($user);

        $this->postJson('/api/v1/shipments/' . $shipmentA->id . '/wallet-preflight', [], $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.shipment_status', Shipment::STATUS_PAYMENT_PENDING);

        $this->postJson('/api/v1/shipments/' . $shipmentB->id . '/wallet-preflight', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_INSUFFICIENT_BALANCE');

        $wallet = BillingWallet::query()->where('account_id', (string) $user->account_id)->where('currency', 'SAR')->firstOrFail();

        $this->assertSame(1, WalletHold::query()->active()->count());
        $this->assertGreaterThan(0, (float) $wallet->reserved_balance);
        $this->assertSame(Shipment::STATUS_PAYMENT_PENDING, (string) $shipmentA->fresh()->status);
        $this->assertSame(Shipment::STATUS_DECLARATION_COMPLETE, (string) $shipmentB->fresh()->status);
    }

    public function test_declaration_required_and_requires_action_block_preflight(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPreflightActor();
        BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'SAR',
        ]);

        $declarationRequiredShipment = $this->createOfferSelectedShipment($user);
        $this->postJson(
            '/api/v1/shipments/' . $declarationRequiredShipment->id . '/wallet-preflight',
            [],
            $this->authHeaders($user)
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_DG_DECLARATION_INCOMPLETE');

        $requiresActionShipment = $this->createOfferSelectedShipment($user);
        $declaration = ContentDeclaration::query()
            ->where('shipment_id', (string) $requiresActionShipment->id)
            ->latest()
            ->firstOrFail();

        $this->postJson(
            '/api/v1/dg/declarations/' . $declaration->id . '/dg-flag',
            ['contains_dangerous_goods' => true],
            $this->authHeaders($user)
        )->assertOk();

        $this->postJson(
            '/api/v1/shipments/' . $requiresActionShipment->id . '/wallet-preflight',
            [],
            $this->authHeaders($user)
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_DG_HOLD_REQUIRED');

        $this->assertSame(0, WalletHold::query()->count());
    }

    public function test_same_tenant_missing_billing_manage_gets_403(): void
    {
        config()->set('features.carrier_fedex', false);

        $account = Account::factory()->organization()->create(['status' => 'active']);
        $owner = $this->createPreflightActorForAccount($account);
        $limited = $this->createPreflightActorForAccount($account, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
            'dg.manage',
            'wallet.balance',
            'billing.view',
        ]);

        BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $account->id,
            'currency' => 'SAR',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($owner);

        $this->postJson(
            '/api/v1/shipments/' . $shipment->id . '/wallet-preflight',
            [],
            $this->authHeaders($limited)
        )->assertStatus(403);
    }

    public function test_cross_tenant_preflight_access_returns_404(): void
    {
        config()->set('features.carrier_fedex', false);

        $accountA = Account::factory()->organization()->create(['status' => 'active']);
        $accountB = Account::factory()->organization()->create(['status' => 'active']);

        $userA = $this->createPreflightActorForAccount($accountA);
        $userB = $this->createPreflightActorForAccount($accountB);

        BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $accountB->id,
            'currency' => 'SAR',
        ]);

        $shipmentB = $this->createDeclarationCompleteShipment($userB);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/shipments/' . $shipmentB->id . '/wallet-preflight')
            ->assertNotFound();
    }

    private function createRetailPricingRule(string $accountId): void
    {
        PricingRule::factory()->create([
            'account_id' => $accountId,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 5,
            'markup_percentage' => 0,
            'service_fee_fixed' => 2,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);
    }

    private function createPreflightActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Preflight Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        return $this->createPreflightActorForAccount($account);
    }

    /**
     * @param array<int, string>|null $permissions
     */
    private function createPreflightActorForAccount(Account $account, ?array $permissions = null): User
    {
        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'en',
        ]);

        $this->grantTenantPermissions($user, $permissions ?? [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
            'dg.manage',
            'wallet.balance',
            'wallet.ledger',
            'wallet.topup',
            'wallet.configure',
            'billing.view',
            'billing.manage',
        ], 'shipment_preflight_actor');

        return $user;
    }

    private function createDeclarationCompleteShipment(User $user): Shipment
    {
        $shipment = $this->createOfferSelectedShipment($user);

        $declaration = ContentDeclaration::query()
            ->where('shipment_id', (string) $shipment->id)
            ->latest()
            ->firstOrFail();

        $this->postJson(
            '/api/v1/dg/declarations/' . $declaration->id . '/dg-flag',
            ['contains_dangerous_goods' => false],
            $this->authHeaders($user)
        )->assertOk();

        $this->postJson(
            '/api/v1/dg/declarations/' . $declaration->id . '/accept-waiver',
            [],
            $this->authHeaders($user)
        )->assertOk();

        return Shipment::query()->findOrFail($shipment->id);
    }

    private function createOfferSelectedShipment(User $user): Shipment
    {
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $quote = $this->fetchRatesForShipment($user, (string) $shipment->id, 'dhl_express');
        $option = RateOption::query()
            ->where('rate_quote_id', (string) $quote->id)
            ->where('is_available', true)
            ->orderBy('retail_rate')
            ->firstOrFail();

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'option_id' => (string) $option->id,
        ], $this->authHeaders($user))->assertOk();

        return Shipment::query()->findOrFail($shipment->id);
    }

    private function createReadyForRatesShipment(User $user): Shipment
    {
        KycVerification::query()->create([
            'account_id' => $user->account_id,
            'status' => KycVerification::STATUS_APPROVED,
            'verification_type' => 'account',
            'verification_level' => 'enhanced',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload(), $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.status', Shipment::STATUS_DRAFT)
            ->json('data');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', Shipment::STATUS_READY_FOR_RATES);

        return Shipment::query()->findOrFail($shipment['id']);
    }

    private function fetchRatesForShipment(User $user, string $shipmentId, string $carrier): RateQuote
    {
        $quoteId = (string) $this->postJson(
            '/api/v1/shipments/' . $shipmentId . '/rates?carrier=' . $carrier,
            [],
            $this->authHeaders($user)
        )
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->json('data.id');

        return RateQuote::query()->findOrFail($quoteId);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentPayload(): array
    {
        return [
            'sender_name' => 'Sender',
            'sender_phone' => '+966500000001',
            'sender_address_1' => 'Origin Street',
            'sender_city' => 'Riyadh',
            'sender_postal_code' => '12211',
            'sender_country' => 'SA',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+966500000002',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'Jeddah',
            'recipient_postal_code' => '21411',
            'recipient_country' => 'SA',
            'parcels' => [[
                'weight' => 2.0,
                'length' => 25,
                'width' => 20,
                'height' => 15,
            ]],
        ];
    }
}
