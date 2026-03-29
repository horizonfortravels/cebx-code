<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\CarrierDocument;
use App\Models\ContentDeclaration;
use App\Models\Notification;
use App\Models\Parcel;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WaiverVersion;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentPostIssuanceJourneyWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);

        WaiverVersion::factory()->english()->create([
            'version' => '2026.03',
            'is_active' => true,
        ]);
    }

    #[DataProvider('postIssuancePersonaProvider')]
    public function test_browser_created_shipment_reaches_post_issuance_view_with_documents_timeline_and_notifications(
        string $accountType,
        string $persona,
        string $routePrefix
    ): void {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody()));

        $user = $this->createPostIssuanceUser($accountType, $persona);
        BillingWallet::factory()->funded(1000)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->actingAs($user, 'web')
            ->post($routePrefix . $shipment->id . '/wallet-preflight')
            ->assertRedirect($routePrefix . $shipment->id);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post($routePrefix . $shipment->id . '/issue')
            ->assertOk()
            ->assertSee('تم إصدار الشحنة لدى الناقل بنجاح.')
            ->assertSee('794699999999')
            ->assertSee(Lang::get('portal_shipments.events.carrier_documents_available', [], 'ar'))
            ->assertSee(Lang::get('portal_shipments.events.shipment_purchased', [], 'ar'))
            ->assertSee('data-testid="public-tracking-link"', false)
            ->assertSee('data-testid="shipment-notifications-link"', false);

        $shipment->refresh();
        $document = CarrierDocument::query()
            ->where('shipment_id', (string) $shipment->id)
            ->firstOrFail();
        $notification = Notification::query()
            ->where('account_id', (string) $user->account_id)
            ->where('user_id', (string) $user->id)
            ->where('entity_type', 'shipment')
            ->where('entity_id', (string) $shipment->id)
            ->latest()
            ->firstOrFail();

        $this->assertSame(Shipment::STATUS_PURCHASED, (string) $shipment->status);

        $this->actingAs($user, 'web')
            ->get($routePrefix . $shipment->id)
            ->assertOk()
            ->assertSee('الحالة المعيارية الحالية')
            ->assertSee(Lang::get('portal_shipments.events.shipment_purchased', [], 'ar'))
            ->assertSee(Lang::get('portal_shipments.events.carrier_documents_available', [], 'ar'))
            ->assertSee('data-testid="public-tracking-link"', false)
            ->assertSee((string) $document->original_filename)
            ->assertSee('الإشعارات المرتبطة بالشحنة')
            ->assertSee((string) $notification->subject)
            ->assertSee('794699999999');

        $this->actingAs($user, 'web')
            ->get($routePrefix . $shipment->id . '/documents')
            ->assertOk()
            ->assertSee((string) $document->original_filename)
            ->assertSee('/documents/' . $document->id . '/view/', false)
            ->assertSee('/documents/' . $document->id . '/', false);

        $this->actingAs($user, 'web')
            ->get($routePrefix . $shipment->id . '/documents/' . $document->id)
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->actingAs($user, 'web')
            ->get('/notifications')
            ->assertOk()
            ->assertSee((string) $notification->subject)
            ->assertSee('794699999999');
    }

    public function test_cross_tenant_post_issuance_browser_surfaces_do_not_leak(): void
    {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody()));

        $owner = $this->createPostIssuanceUser('organization', 'organization_owner');
        $intruder = $this->createPostIssuanceUser('organization', 'organization_owner');

        BillingWallet::factory()->funded(1000)->create([
            'account_id' => (string) $owner->account_id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($owner);

        $this->actingAs($owner, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/wallet-preflight')
            ->assertRedirect('/b2b/shipments/' . $shipment->id);

        $this->actingAs($owner, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/issue')
            ->assertRedirect('/b2b/shipments/' . $shipment->id);

        $shipment->refresh();
        $trackingNumber = (string) ($shipment->tracking_number ?? $shipment->carrier_tracking_number ?? '794699999999');

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertNotFound();

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertNotFound();

        $this->actingAs($intruder, 'web')
            ->get('/notifications')
            ->assertOk()
            ->assertDontSee($trackingNumber);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function postIssuancePersonaProvider(): array
    {
        return [
            'individual' => ['individual', 'individual', '/b2c/shipments/'],
            'organization_owner' => ['organization', 'organization_owner', '/b2b/shipments/'],
        ];
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

    private function createPostIssuanceUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Post Issue ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Post Issue ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.read',
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
            'dg.manage',
            'tracking.read',
            'billing.view',
            'billing.manage',
            'notifications.read',
        ], 'shipment_post_issuance_web_' . $persona);

        return $user;
    }

    private function createDeclarationCompleteShipment(User $user): Shipment
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'created_by' => (string) $user->id,
            'status' => Shipment::STATUS_DECLARATION_COMPLETE,
            'type' => 'international',
            'sender_name' => 'Sender',
            'sender_phone' => '+966500000001',
            'sender_address_1' => 'Origin Street',
            'sender_city' => 'Riyadh',
            'sender_postal_code' => '12211',
            'sender_country' => 'SA',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'New York',
            'recipient_postal_code' => '10001',
            'recipient_country' => 'US',
            'is_international' => true,
            'has_dangerous_goods' => false,
            'total_weight' => 1.5,
            'chargeable_weight' => 1.5,
            'carrier_code' => 'fedex',
        ];

        if (Schema::hasColumn('shipments', 'currency')) {
            $attributes['currency'] = 'USD';
        }

        if (Schema::hasColumn('shipments', 'total_charge')) {
            $attributes['total_charge'] = 345.15;
        } elseif (Schema::hasColumn('shipments', 'total_cost')) {
            $attributes['total_cost'] = 345.15;
        }

        if (Schema::hasColumn('shipments', 'service_code')) {
            $attributes['service_code'] = 'INTERNATIONAL_PRIORITY';
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $attributes['service_name'] = 'FedEx International Priority';
        }

        $shipment = Shipment::factory()->create($attributes);

        Parcel::factory()->create([
            'shipment_id' => (string) $shipment->id,
            'weight' => 1.5,
            'length' => 20,
            'width' => 15,
            'height' => 10,
        ]);

        $quote = RateQuote::factory()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'origin_country' => 'SA',
            'origin_city' => 'Riyadh',
            'destination_country' => 'US',
            'destination_city' => 'New York',
            'currency' => 'USD',
            'requested_by' => (string) $user->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        $option = RateOption::query()->create([
            'rate_quote_id' => (string) $quote->id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'INTERNATIONAL_PRIORITY',
            'service_name' => 'FedEx International Priority',
            'net_rate' => 300.00,
            'fuel_surcharge' => 45.15,
            'other_surcharges' => 0.00,
            'total_net_rate' => 345.15,
            'markup_amount' => 0.00,
            'service_fee' => 0.00,
            'retail_rate_before_rounding' => 345.15,
            'retail_rate' => 345.15,
            'profit_margin' => 0.00,
            'currency' => 'USD',
            'estimated_days_min' => 3,
            'estimated_days_max' => 3,
            'estimated_delivery_at' => now()->addDays(3),
            'pricing_breakdown' => ['stage' => 'retail'],
            'rule_evaluation_log' => [
                'pricing_stage' => 'retail',
                'pricing_path' => 'shipment_quote',
            ],
            'is_available' => true,
        ]);

        $quote->update([
            'selected_option_id' => (string) $option->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        $shipment->update([
            'rate_quote_id' => (string) $quote->id,
            'selected_rate_option_id' => (string) $option->id,
            'status' => Shipment::STATUS_DECLARATION_COMPLETE,
        ]);

        ContentDeclaration::query()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'contains_dangerous_goods' => false,
            'dg_flag_declared' => true,
            'status' => ContentDeclaration::STATUS_COMPLETED,
            'waiver_accepted' => true,
            'declared_by' => (string) $user->id,
            'declared_at' => now(),
            'waiver_accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'locale' => 'ar',
        ]);

        return $shipment->fresh([
            'selectedRateOption',
            'rateQuote',
            'balanceReservation',
            'contentDeclaration',
            'parcels',
        ]);
    }

    /**
     * @param array<string, mixed> $shipBody
     * @return array<string, \Closure>
     */
    private function fedexShipFakeResponses(array $shipBody, int $shipStatus = 200): array
    {
        return [
            'https://apis-sandbox.fedex.com/oauth/token' => fn () => Http::response([
                'access_token' => 'fedex-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/ship/v1/shipments' => fn () => Http::response($shipBody, $shipStatus),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipSuccessBody(): array
    {
        return [
            'transactionId' => 'fedex-ship-tx-001',
            'customerTransactionId' => 'REQ-FDX-CREATE-001',
            'output' => [
                'alerts' => [],
                'transactionShipments' => [[
                    'serviceType' => 'INTERNATIONAL_PRIORITY',
                    'serviceName' => 'FedEx International Priority',
                    'masterTrackingNumber' => '794699999999',
                    'alerts' => [],
                    'pieceResponses' => [[
                        'trackingNumber' => '794699999999',
                        'alerts' => [],
                    ]],
                    'completedShipmentDetail' => [
                        'carrierCode' => 'FDXE',
                        'masterTrackingNumber' => '794699999999',
                    ],
                    'shipmentDocuments' => [[
                        'contentKey' => 'LABEL',
                        'copiesToPrint' => 1,
                        'encodedLabel' => base64_encode('fake-fedex-label'),
                    ]],
                ]],
            ],
        ];
    }
}
