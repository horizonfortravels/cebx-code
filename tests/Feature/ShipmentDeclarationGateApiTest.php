<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\KycVerification;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WaiverVersion;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentDeclarationGateApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WaiverVersion::factory()->english()->create([
            'version' => '2026.03',
            'is_active' => true,
        ]);
    }

    public function test_selected_offer_creates_declaration_required_gate_and_blocks_continuation(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentFlowActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        [$quote, $option] = $this->fetchRatesAndResolveOption($user, (string) $shipment['id'], 'dhl_express');

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'option_id' => (string) $option->id,
        ], $this->authHeaders($user))->assertOk();

        $shipmentRecord = Shipment::query()->findOrFail($shipment['id']);
        $declaration = ContentDeclaration::query()
            ->where('account_id', (string) $user->account_id)
            ->where('shipment_id', (string) $shipment['id'])
            ->latest()
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_DECLARATION_REQUIRED, (string) $shipmentRecord->status);
        $this->assertSame((string) $user->id, (string) $declaration->declared_by);
        $this->assertSame('127.0.0.1', (string) $declaration->ip_address);
        $this->assertNotNull($declaration->declared_at);
        $this->assertFalse((bool) $declaration->waiver_accepted);
        $this->assertCount(1, DgAuditLog::query()
            ->where('declaration_id', (string) $declaration->id)
            ->where('action', DgAuditLog::ACTION_CREATED)
            ->get());

        $this->postJson('/api/v1/dg/validate-issuance', [
            'shipment_id' => (string) $shipment['id'],
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('error_code', 'ERR_DG_DECLARATION_INCOMPLETE');
    }

    public function test_api_exposes_clear_declaration_semantics_for_selected_offer_gate(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentFlowActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $declaration = $this->selectOfferAndGetDeclaration($user, (string) $shipment['id']);

        $this->getJson('/api/v1/dg/shipments/' . $shipment['id'] . '/declaration', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.id', (string) $declaration->id)
            ->assertJsonPath('data.shipment_workflow_state', Shipment::STATUS_DECLARATION_REQUIRED)
            ->assertJsonPath('data.declaration_complete', false)
            ->assertJsonPath('data.is_blocked', false)
            ->assertJsonPath('data.requires_disclaimer', false);
    }

    public function test_dg_yes_puts_shipment_into_requires_action_and_blocks_continuation(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentFlowActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $declaration = $this->selectOfferAndGetDeclaration($user, (string) $shipment['id']);

        $this->postJson('/api/v1/dg/declarations/' . $declaration->id . '/dg-flag', [
            'contains_dangerous_goods' => true,
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', ContentDeclaration::STATUS_HOLD_DG);

        $shipmentRecord = Shipment::query()->findOrFail($shipment['id']);
        $this->assertSame(Shipment::STATUS_REQUIRES_ACTION, (string) $shipmentRecord->status);
        $this->assertTrue((bool) $shipmentRecord->has_dangerous_goods);
        $this->assertNotNull($shipmentRecord->status_reason);

        $this->postJson('/api/v1/dg/validate-issuance', [
            'shipment_id' => (string) $shipment['id'],
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('error_code', 'ERR_DG_HOLD_REQUIRED');
    }

    public function test_dg_no_without_disclaimer_acceptance_is_rejected(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentFlowActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $declaration = $this->selectOfferAndGetDeclaration($user, (string) $shipment['id']);

        $this->postJson('/api/v1/dg/declarations/' . $declaration->id . '/dg-flag', [
            'contains_dangerous_goods' => false,
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', ContentDeclaration::STATUS_PENDING);

        $shipmentRecord = Shipment::query()->findOrFail($shipment['id']);
        $this->assertSame(Shipment::STATUS_DECLARATION_REQUIRED, (string) $shipmentRecord->status);

        $this->postJson('/api/v1/dg/validate-issuance', [
            'shipment_id' => (string) $shipment['id'],
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('error_code', 'ERR_DG_DISCLAIMER_REQUIRED');
    }

    public function test_dg_no_with_disclaimer_acceptance_completes_gate_and_stores_audit_fields(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentFlowActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $declaration = $this->selectOfferAndGetDeclaration($user, (string) $shipment['id']);

        $this->postJson('/api/v1/dg/declarations/' . $declaration->id . '/dg-flag', [
            'contains_dangerous_goods' => false,
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/dg/declarations/' . $declaration->id . '/accept-waiver', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.waiver_accepted', true)
            ->assertJsonPath('data.status', ContentDeclaration::STATUS_COMPLETED);

        $declaration->refresh();
        $shipmentRecord = Shipment::query()->findOrFail($shipment['id']);

        $this->assertSame(Shipment::STATUS_DECLARATION_COMPLETE, (string) $shipmentRecord->status);
        $this->assertNotNull($declaration->waiver_version_id);
        $this->assertNotNull($declaration->waiver_accepted_at);
        $this->assertNotEmpty((string) $declaration->waiver_hash_snapshot);
        $this->assertNotEmpty((string) $declaration->waiver_text_snapshot);
        $this->assertSame((string) $user->id, (string) $declaration->declared_by);
        $this->assertSame('127.0.0.1', (string) $declaration->ip_address);

        $actions = DgAuditLog::query()
            ->where('declaration_id', (string) $declaration->id)
            ->pluck('action')
            ->all();

        $this->assertContains(DgAuditLog::ACTION_CREATED, $actions);
        $this->assertContains(DgAuditLog::ACTION_DG_FLAG_SET, $actions);
        $this->assertContains(DgAuditLog::ACTION_WAIVER_ACCEPTED, $actions);
        $this->assertContains(DgAuditLog::ACTION_COMPLETED, $actions);

        $this->postJson('/api/v1/dg/validate-issuance', [
            'shipment_id' => (string) $shipment['id'],
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.declaration_id', (string) $declaration->id);
    }

    public function test_same_tenant_missing_manage_permission_gets_403_on_dg_write(): void
    {
        config()->set('features.carrier_fedex', false);

        $account = Account::factory()->organization()->create(['status' => 'active']);
        $owner = $this->createShipmentFlowActorForAccount($account);
        $limited = $this->createShipmentFlowActorForAccount($account, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
        ]);

        $shipment = $this->createReadyForRatesShipment($owner);
        $this->createRetailPricingRule((string) $account->id);
        $declaration = $this->selectOfferAndGetDeclaration($owner, (string) $shipment['id']);

        $this->postJson('/api/v1/dg/declarations/' . $declaration->id . '/dg-flag', [
            'contains_dangerous_goods' => false,
        ], $this->authHeaders($limited))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    public function test_cross_tenant_dg_write_returns_404(): void
    {
        config()->set('features.carrier_fedex', false);

        $accountA = Account::factory()->organization()->create(['status' => 'active']);
        $accountB = Account::factory()->organization()->create(['status' => 'active']);

        $userA = $this->createShipmentFlowActorForAccount($accountA);
        $userB = $this->createShipmentFlowActorForAccount($accountB);

        $shipmentB = $this->createReadyForRatesShipment($userB);
        $this->createRetailPricingRule((string) $accountB->id);
        $declarationB = $this->selectOfferAndGetDeclaration($userB, (string) $shipmentB['id']);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/dg/declarations/' . $declarationB->id . '/dg-flag', [
            'contains_dangerous_goods' => false,
        ])->assertNotFound();
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

    private function createShipmentFlowActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'DG Flow Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        return $this->createShipmentFlowActorForAccount($account);
    }

    /**
     * @param array<int, string>|null $permissions
     */
    private function createShipmentFlowActorForAccount(Account $account, ?array $permissions = null): User
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
        ], 'shipment_declaration_gate');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function createReadyForRatesShipment(User $user): array
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

        return $shipment;
    }

    /**
     * @return array{0: RateQuote, 1: RateOption}
     */
    private function fetchRatesAndResolveOption(User $user, string $shipmentId, string $carrier): array
    {
        $quoteId = (string) $this->postJson(
            '/api/v1/shipments/' . $shipmentId . '/rates?carrier=' . $carrier,
            [],
            $this->authHeaders($user)
        )
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->json('data.id');

        $quote = RateQuote::query()->findOrFail($quoteId);
        $option = RateOption::query()
            ->where('rate_quote_id', (string) $quote->id)
            ->where('is_available', true)
            ->orderBy('retail_rate')
            ->firstOrFail();

        return [$quote, $option];
    }

    private function selectOfferAndGetDeclaration(User $user, string $shipmentId): ContentDeclaration
    {
        [$quote, $option] = $this->fetchRatesAndResolveOption($user, $shipmentId, 'dhl_express');

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'option_id' => (string) $option->id,
        ], $this->authHeaders($user))->assertOk();

        return ContentDeclaration::query()
            ->where('account_id', (string) $user->account_id)
            ->where('shipment_id', $shipmentId)
            ->latest()
            ->firstOrFail();
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
            'recipient_phone' => '+971501234567',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'Dubai',
            'recipient_postal_code' => '00000',
            'recipient_country' => 'AE',
            'parcels' => [[
                'weight' => 2.0,
                'length' => 25,
                'width' => 20,
                'height' => 15,
            ]],
        ];
    }
}
