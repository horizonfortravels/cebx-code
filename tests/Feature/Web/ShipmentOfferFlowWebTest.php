<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentOfferFlowWebTest extends TestCase
{
    public function test_b2c_browser_offer_fetch_uses_fedex_when_real_fedex_is_enabled(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());
        $this->configureFedex();

        $user = $this->createPortalUser('individual', 'individual');
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $this->actingAs($user, 'web')
            ->post('/b2c/shipments/' . $shipment['id'] . '/offers/fetch')
            ->assertRedirect('/b2c/shipments/' . $shipment['id'] . '/offers');

        $quote = $this->latestQuoteForShipment((string) $shipment['id']);

        $this->assertSame('fedex', data_get($quote->request_metadata, 'carrier_code'));
        $this->assertGreaterThan(0, $quote->options->count());
        $this->assertTrue($quote->options->every(fn (RateOption $option): bool => (string) $option->carrier_code === 'fedex'));
        $this->assertFalse($quote->options->contains(fn (RateOption $option): bool => in_array((string) $option->carrier_code, ['aramex', 'dhl_express'], true)));
    }

    public function test_b2c_individual_user_can_view_offers_for_own_shipment(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('individual', 'individual');
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);
        $this->fetchRatesForShipment($user, (string) $shipment['id'], 'dhl_express');

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/' . $shipment['id'] . '/offers')
            ->assertOk()
            ->assertSee('مقارنة عروض الشحن')
            ->assertSee('اختيار هذا العرض')
            ->assertSee('السعر المعروض');
    }

    #[DataProvider('organizationPersonaProvider')]
    public function test_b2b_organization_personas_can_view_and_select_offers(string $persona): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('organization', $persona);
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);
        $quote = $this->fetchRatesForShipment($user, (string) $shipment['id'], 'dhl_express');
        $option = RateOption::query()
            ->where('rate_quote_id', (string) $quote->id)
            ->where('is_available', true)
            ->orderBy('retail_rate')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment['id'] . '/offers')
            ->assertOk()
            ->assertSee('مقارنة عروض الشحن')
            ->assertSee('اختيار هذا العرض');

        $this->followingRedirects()
            ->actingAs($user, 'web')
            ->post('/b2b/shipments/' . $shipment['id'] . '/offers/select', [
                'option_id' => (string) $option->id,
            ])
            ->assertOk()
            ->assertSee('إقرار المحتوى والتصريح بالمواد الخطرة')
            ->assertSee('تم تثبيت العرض المختار على هذه الشحنة.')
            ->assertSee('الإقرار القانوني الإلزامي');

        $shipmentRecord = Shipment::query()->findOrFail($shipment['id']);
        $this->assertSame(Shipment::STATUS_DECLARATION_REQUIRED, (string) $shipmentRecord->status);
        $this->assertSame((string) $quote->id, (string) $shipmentRecord->rate_quote_id);
        $this->assertSame((string) $option->id, (string) $shipmentRecord->selected_rate_option_id);
    }

    public function test_b2b_browser_offer_fetch_uses_fedex_when_real_fedex_is_enabled(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());
        $this->configureFedex();

        $user = $this->createPortalUser('organization', 'organization_owner');
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $this->actingAs($user, 'web')
            ->post('/b2b/shipments/' . $shipment['id'] . '/offers/fetch')
            ->assertRedirect('/b2b/shipments/' . $shipment['id'] . '/offers');

        $quote = $this->latestQuoteForShipment((string) $shipment['id']);

        $this->assertSame('fedex', data_get($quote->request_metadata, 'carrier_code'));
        $this->assertGreaterThan(0, $quote->options->count());
        $this->assertTrue($quote->options->every(fn (RateOption $option): bool => (string) $option->carrier_code === 'fedex'));
        $this->assertFalse($quote->options->contains(fn (RateOption $option): bool => in_array((string) $option->carrier_code, ['aramex', 'dhl_express'], true)));
    }

    public function test_cross_tenant_browser_offer_access_is_denied(): void
    {
        config()->set('features.carrier_fedex', false);

        $ownerA = $this->createPortalUser('organization', 'organization_owner');
        $ownerB = $this->createPortalUser('organization', 'organization_owner');

        $shipmentB = $this->createReadyForRatesShipment($ownerB);
        $this->createRetailPricingRule((string) $ownerB->account_id);
        $this->fetchRatesForShipment($ownerB, (string) $shipmentB['id'], 'dhl_express');

        $this->actingAs($ownerA, 'web')
            ->get('/b2b/shipments/' . $shipmentB['id'] . '/offers')
            ->assertNotFound();
    }

    public function test_browser_offer_fetch_keeps_simulated_fallback_when_fedex_is_disabled(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createPortalUser('organization', 'organization_owner');
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $this->actingAs($user, 'web')
            ->post('/b2b/shipments/' . $shipment['id'] . '/offers/fetch')
            ->assertRedirect('/b2b/shipments/' . $shipment['id'] . '/offers');

        $quote = $this->latestQuoteForShipment((string) $shipment['id']);
        $carriers = $quote->options->pluck('carrier_code')->unique()->values()->all();

        $this->assertNull(data_get($quote->request_metadata, 'carrier_code'));
        $this->assertContains('aramex', $carriers);
        $this->assertContains('dhl_express', $carriers);
        $this->assertNotContains('fedex', $carriers);
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
                'name' => 'B2C Offer ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Offer ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ], 'shipment_offer_web_' . $persona);

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

    private function latestQuoteForShipment(string $shipmentId): RateQuote
    {
        return RateQuote::query()
            ->where('shipment_id', $shipmentId)
            ->with('options')
            ->latest('created_at')
            ->firstOrFail();
    }

    private function configureFedex(): void
    {
        config()->set('features.carrier_fedex', true);
        config()->set('services.fedex.client_id', 'fedex-test-client');
        config()->set('services.fedex.client_secret', 'fedex-test-secret');
        config()->set('services.fedex.account_number', '123456789');
        config()->set('services.fedex.base_url', 'https://apis-sandbox.fedex.com');
        config()->set('services.fedex.oauth_url', 'https://apis-sandbox.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    /**
     * @return array<string, \Closure>
     */
    private function fedexFakeResponses(): array
    {
        return [
            'https://apis-sandbox.fedex.com/oauth/token' => fn () => Http::response([
                'access_token' => 'fedex-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => fn () => Http::response([
                'output' => [
                    'serviceOptions' => [
                        ['key' => 'INTERNATIONAL_PRIORITY', 'displayText' => 'FedEx International Priority'],
                    ],
                    'alerts' => [[
                        'code' => 'VIRTUAL.RESPONSE',
                        'message' => 'This is a Virtual Response.',
                        'alertType' => 'NOTE',
                    ]],
                ],
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/transittimes' => fn () => Http::response([
                'output' => [
                    'transitTimes' => [[
                        'transitTimeDetails' => [[
                            'serviceType' => 'INTERNATIONAL_PRIORITY',
                            'serviceName' => 'FedEx International Priority',
                            'commit' => [
                                'commitDate' => '2026-03-15T18:00:00Z',
                                'transitTime' => 'THREE_DAYS',
                                'transitDays' => 3,
                            ],
                        ]],
                    ]],
                ],
            ], 200),
            'https://apis-sandbox.fedex.com/rate/v1/rates/quotes' => fn () => Http::response([
                'output' => [
                    'alerts' => [[
                        'code' => 'VIRTUAL.RESPONSE',
                        'message' => 'This is a Virtual Response.',
                        'alertType' => 'NOTE',
                    ]],
                    'rateReplyDetails' => [[
                        'serviceType' => 'INTERNATIONAL_PRIORITY',
                        'serviceName' => 'FedEx International Priority',
                        'operationalDetail' => [
                            'commitDate' => '2026-03-15T18:00:00Z',
                            'transitTime' => 'THREE_DAYS',
                        ],
                        'ratedShipmentDetails' => [[
                            'rateType' => 'ACCOUNT',
                            'totalBaseCharge' => 300.00,
                            'totalNetCharge' => 345.15,
                            'shipmentRateDetail' => [
                                'currency' => 'USD',
                                'surCharges' => [[
                                    'type' => 'FUEL',
                                    'amount' => 45.15,
                                ]],
                            ],
                        ]],
                    ]],
                ],
            ], 200),
        ];
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
