<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WaiverVersion;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentDeclarationFlowWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WaiverVersion::factory()->english()->create([
            'version' => '2026.03',
            'is_active' => true,
        ]);
    }

    public function test_b2c_individual_user_can_view_declaration_step_for_own_shipment(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('individual', 'individual');
        $shipment = $this->selectOfferForShipment($user, 'dhl_express');

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/' . $shipment->id . '/declaration')
            ->assertOk()
            ->assertSee('إقرار المحتوى والتصريح بالمواد الخطرة')
            ->assertSee('الإقرار القانوني الإلزامي')
            ->assertSee('هل تحتوي هذه الشحنة على مواد خطرة؟');
    }

    public function test_browser_dg_yes_shows_hold_guidance(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('organization', 'organization_owner');
        $shipment = $this->selectOfferForShipment($user, 'dhl_express');

        $this->actingAs($user, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/declaration', [
                'contains_dangerous_goods' => 'yes',
            ])
            ->assertRedirect('/b2b/shipments/' . $shipment->id . '/declaration');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/declaration')
            ->assertOk()
            ->assertSee('تم تعليق المسار العادي لهذه الشحنة')
            ->assertSee('تواصل مع فريق الدعم أو العمليات');

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_REQUIRES_ACTION, (string) $shipment->status);
    }

    public function test_browser_dg_no_requires_disclaimer_acceptance(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('organization', 'organization_owner');
        $shipment = $this->selectOfferForShipment($user, 'dhl_express');

        $response = $this->actingAs($user, 'web')
            ->from('/b2b/shipments/' . $shipment->id . '/declaration')
            ->post('/b2b/shipments/' . $shipment->id . '/declaration', [
                'contains_dangerous_goods' => 'no',
            ]);

        $response->assertRedirect('/b2b/shipments/' . $shipment->id . '/declaration');
        $response->assertSessionHasErrors('accept_disclaimer');

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_DECLARATION_REQUIRED, (string) $shipment->status);
    }

    #[DataProvider('organizationPersonaProvider')]
    public function test_b2b_request_flow_personas_can_complete_non_dg_declaration(string $persona): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('organization', $persona);
        $shipment = $this->selectOfferForShipment($user, 'dhl_express');

        $this->actingAs($user, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/declaration', [
                'contains_dangerous_goods' => 'no',
                'accept_disclaimer' => '1',
            ])
            ->assertRedirect('/b2b/shipments/' . $shipment->id . '/declaration');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/declaration')
            ->assertOk()
            ->assertSee('تم حفظ الإقرار القانوني بنجاح')
            ->assertSee('الشحنة أصبحت جاهزة للمرحلة التالية');

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_DECLARATION_COMPLETE, (string) $shipment->status);
    }

    public function test_cross_tenant_browser_declaration_access_is_denied(): void
    {
        config()->set('features.carrier_fedex', false);

        $ownerA = $this->createPortalUser('organization', 'organization_owner');
        $ownerB = $this->createPortalUser('organization', 'organization_owner');

        $shipmentB = $this->selectOfferForShipment($ownerB, 'dhl_express');

        $this->actingAs($ownerA, 'web')
            ->get('/b2b/shipments/' . $shipmentB->id . '/declaration')
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function organizationPersonaProvider(): array
    {
        return [
            'organization_owner' => ['organization_owner'],
            'organization_admin' => ['organization_admin'],
            'staff' => ['staff'],
        ];
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

    private function createPortalUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C DG ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B DG ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ], 'shipment_declaration_web_' . $persona);

        return $user;
    }

    private function selectOfferForShipment(User $user, string $carrier): Shipment
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
            ->json('data');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', Shipment::STATUS_READY_FOR_RATES);

        $this->createRetailPricingRule((string) $user->account_id);

        $quoteId = (string) $this->postJson(
            '/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=' . $carrier,
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

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'option_id' => (string) $option->id,
        ], $this->authHeaders($user))->assertOk();

        return Shipment::query()->findOrFail($shipment['id']);
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
