<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Parcel;
use App\Models\PricingRule;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RateApiTest — Integration tests for RT module API endpoints
 */
class RateApiTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected User $owner;
    protected User $manager;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['status' => 'active', 'kyc_status' => 'verified']);
        $this->owner   = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => true]);
        $this->manager = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);
        $this->member  = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);

        $this->manager->grantPermission('shipments:manage');
        $this->manager->grantPermission('rates:manage_rules');
        $this->manager->grantPermission('rates:view_breakdown');

        PricingRule::factory()->default()->create(['account_id' => $this->account->id]);
    }

    private function createShipment(): Shipment
    {
        $shipment = Shipment::factory()->create([
            'account_id'       => $this->account->id,
            'created_by'       => $this->owner->id,
            'status'           => Shipment::STATUS_DRAFT,
            'sender_country'   => 'SA',
            'recipient_country'=> 'SA',
            'total_weight'     => 3.0,
            'chargeable_weight'=> 3.0,
            'parcels_count'    => 1,
        ]);
        Parcel::create(['shipment_id' => $shipment->id, 'sequence' => 1, 'weight' => 3.0]);
        return $shipment;
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/{id}/rates — FR-RT-001
    // ═══════════════════════════════════════════════════════════════

    public function test_fetch_rates_as_owner(): void
    {
        $shipment = $this->createShipment();

        $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->assertOk()
             ->assertJsonPath('data.status', 'completed')
             ->assertJsonStructure(['data' => ['id', 'options', 'expires_at', 'correlation_id']]);
    }

    public function test_fetch_rates_specific_carrier(): void
    {
        $shipment = $this->createShipment();

        $response = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates?carrier=dhl_express")
             ->assertOk();

        $options = $response->json('data.options');
        foreach ($options as $opt) {
            $this->assertEquals('dhl_express', $opt['carrier_code']);
        }
    }

    public function test_fetch_rates_returns_multiple_options(): void
    {
        $shipment = $this->createShipment();

        $response = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->assertOk();

        $this->assertGreaterThan(1, $response->json('data.options_count'));
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/shipments/{id}/reprice — FR-RT-007
    // ═══════════════════════════════════════════════════════════════

    public function test_reprice_creates_new_quote(): void
    {
        $shipment = $this->createShipment();

        $first = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        $second = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/reprice")
             ->assertOk()
             ->json('data.id');

        $this->assertNotEquals($first, $second);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/rate-quotes/{id} — FR-RT-005/011
    // ═══════════════════════════════════════════════════════════════

    public function test_show_quote_owner_full_details(): void
    {
        $shipment = $this->createShipment();
        $quoteId = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        $this->actingAs($this->owner)
             ->getJson("/api/v1/rate-quotes/{$quoteId}")
             ->assertOk()
             ->assertJsonStructure(['data' => ['quote', 'options', 'is_expired', 'expires_in_seconds']]);
    }

    public function test_show_quote_member_restricted(): void
    {
        $shipment = $this->createShipment();
        $quoteId = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        $response = $this->actingAs($this->member)
             ->getJson("/api/v1/rate-quotes/{$quoteId}")
             ->assertOk();

        // Member should not see breakdown details
        $options = $response->json('data.options');
        foreach ($options as $opt) {
            $this->assertArrayNotHasKey('pricing_breakdown', $opt);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/rate-quotes/{id}/select — FR-RT-010
    // ═══════════════════════════════════════════════════════════════

    public function test_select_option_manually(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data');

        $optionId = $quote['options'][0]['id'];

        $this->actingAs($this->owner)
             ->postJson("/api/v1/rate-quotes/{$quote['id']}/select", ['option_id' => $optionId])
             ->assertOk()
             ->assertJsonPath('data.status', 'selected')
             ->assertJsonPath('data.selected_option_id', $optionId);
    }

    public function test_auto_select_cheapest(): void
    {
        $shipment = $this->createShipment();
        $quoteId = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        $this->actingAs($this->owner)
             ->postJson("/api/v1/rate-quotes/{$quoteId}/select", ['strategy' => 'cheapest'])
             ->assertOk()
             ->assertJsonPath('data.status', 'selected');
    }

    public function test_auto_select_fastest(): void
    {
        $shipment = $this->createShipment();
        $quoteId = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        $this->actingAs($this->owner)
             ->postJson("/api/v1/rate-quotes/{$quoteId}/select", ['strategy' => 'fastest'])
             ->assertOk()
             ->assertJsonPath('data.status', 'selected');
    }

    public function test_select_expired_quote_rejected(): void
    {
        $shipment = $this->createShipment();
        $quoteId = $this->actingAs($this->owner)
             ->postJson("/api/v1/shipments/{$shipment->id}/rates")
             ->json('data.id');

        // Force expire
        RateQuote::where('id', $quoteId)->update(['expires_at' => now()->subMinute(), 'is_expired' => true]);

        $this->actingAs($this->owner)
             ->postJson("/api/v1/rate-quotes/{$quoteId}/select", ['strategy' => 'cheapest'])
             ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // Pricing Rules CRUD — FR-RT-008
    // ═══════════════════════════════════════════════════════════════

    public function test_list_pricing_rules(): void
    {
        PricingRule::factory()->count(3)->create(['account_id' => $this->account->id]);

        $this->actingAs($this->owner)
             ->getJson('/api/v1/pricing-rules')
             ->assertOk()
             ->assertJsonCount(4, 'data'); // 3 + 1 default from setUp
    }

    public function test_create_pricing_rule(): void
    {
        $this->actingAs($this->owner)
             ->postJson('/api/v1/pricing-rules', [
                 'name'              => 'قاعدة DHL محلي',
                 'carrier_code'      => 'dhl_express',
                 'markup_type'       => 'percentage',
                 'markup_percentage' => 18.0,
                 'priority'          => 50,
             ])
             ->assertStatus(201)
             ->assertJsonPath('data.carrier_code', 'dhl_express')
             ->assertJsonPath('data.markup_percentage', '18.0000');
    }

    public function test_update_pricing_rule(): void
    {
        $rule = PricingRule::factory()->create(['account_id' => $this->account->id]);

        $this->actingAs($this->owner)
             ->putJson("/api/v1/pricing-rules/{$rule->id}", [
                 'markup_percentage' => 25.0,
                 'is_active'         => false,
             ])
             ->assertOk()
             ->assertJsonPath('data.markup_percentage', '25.0000')
             ->assertJsonPath('data.is_active', false);
    }

    public function test_delete_pricing_rule(): void
    {
        $rule = PricingRule::factory()->create(['account_id' => $this->account->id]);

        $this->actingAs($this->owner)
             ->deleteJson("/api/v1/pricing-rules/{$rule->id}")
             ->assertOk();

        $this->assertSoftDeleted('pricing_rules', ['id' => $rule->id]);
    }

    public function test_member_cannot_create_rule(): void
    {
        $this->actingAs($this->member)
             ->postJson('/api/v1/pricing-rules', [
                 'name'        => 'Test',
                 'markup_type' => 'percentage',
             ])
             ->assertStatus(422);
    }

    public function test_manager_with_permission_can_create_rule(): void
    {
        $this->actingAs($this->manager)
             ->postJson('/api/v1/pricing-rules', [
                 'name'              => 'Manager Rule',
                 'markup_type'       => 'fixed',
                 'markup_fixed'      => 10,
             ])
             ->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_create_rule_validation(): void
    {
        $this->actingAs($this->owner)
             ->postJson('/api/v1/pricing-rules', [])
             ->assertStatus(422);
    }
}
