<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\CarrierShipment;
use App\Models\ContentDeclaration;
use App\Models\Parcel;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WaiverVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentCompletionFlowWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WaiverVersion::factory()->english()->create([
            'version' => '2026.03',
            'is_active' => true,
        ]);
    }

    public function test_b2c_individual_user_can_trigger_wallet_preflight_from_browser(): void
    {
        $user = $this->createCompletionUser('individual', 'individual');
        BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/' . $shipment->id)
            ->assertOk()
            ->assertSee('data-testid="wallet-preflight-button"', false);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post('/b2c/shipments/' . $shipment->id . '/wallet-preflight')
            ->assertOk()
            ->assertSee('تم حجز مبلغ الشحنة من المحفظة بنجاح.')
            ->assertSee('data-testid="carrier-issue-button"', false);

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_PAYMENT_PENDING, (string) $shipment->status);
        $this->assertNotNull($shipment->balance_reservation_id);
    }

    #[DataProvider('browserCompletionPersonaProvider')]
    public function test_request_flow_personas_can_issue_shipment_from_browser(
        string $accountType,
        string $persona,
        string $routePrefix
    ): void {
        $this->configureFedex();

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake($this->fedexShipFakeResponses($this->shipSuccessBody()));

        $user = $this->createCompletionUser($accountType, $persona);
        BillingWallet::factory()->funded(1000)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post($routePrefix . $shipment->id . '/wallet-preflight')
            ->assertOk()
            ->assertSee('تم حجز مبلغ الشحنة من المحفظة بنجاح.');

        $this->actingAs($user, 'web')
            ->get($routePrefix . $shipment->id)
            ->assertOk()
            ->assertSee('data-testid="carrier-issue-button"', false);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post($routePrefix . $shipment->id . '/issue')
            ->assertOk()
            ->assertSee('تم إصدار الشحنة لدى الناقل بنجاح.')
            ->assertSee('794699999999');

        $shipment->refresh();
        $carrierShipment = CarrierShipment::query()->where('shipment_id', (string) $shipment->id)->firstOrFail();

        $this->assertSame(Shipment::STATUS_PURCHASED, (string) $shipment->status);
        $this->assertSame((string) $carrierShipment->id, (string) $shipment->carrierShipment?->id);
        $this->assertSame('794699999999', (string) $carrierShipment->tracking_number);
    }

    public function test_browser_insufficient_funds_path_is_readable(): void
    {
        $user = $this->createCompletionUser('organization', 'organization_owner');
        BillingWallet::factory()->funded(10)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($user);

        $this->actingAs($user, 'web')
            ->followingRedirects()
            ->post('/b2b/shipments/' . $shipment->id . '/wallet-preflight')
            ->assertOk()
            ->assertSee('رصيد المحفظة غير كافٍ لهذه الشحنة.')
            ->assertSee('اشحن المحفظة أو اختر عرضًا أقل تكلفة ثم أعد المحاولة.');

        $shipment->refresh();
        $this->assertSame(Shipment::STATUS_DECLARATION_COMPLETE, (string) $shipment->status);
        $this->assertNull($shipment->balance_reservation_id);
    }

    public function test_same_tenant_missing_completion_permissions_gets_403(): void
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Completion Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $owner = $this->createCompletionUserForAccount($account, 'organization_owner');
        $limited = $this->createCompletionUserForAccount($account, 'staff_limited', [
            'shipments.read',
            'tracking.read',
        ]);

        BillingWallet::factory()->funded(1000)->create([
            'account_id' => (string) $account->id,
            'currency' => 'USD',
        ]);

        $shipment = $this->createDeclarationCompleteShipment($owner);

        $this->actingAs($limited, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertOk();

        $this->actingAs($limited, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/wallet-preflight')
            ->assertForbidden();

        $this->actingAs($owner, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/wallet-preflight')
            ->assertRedirect('/b2b/shipments/' . $shipment->id);

        $this->actingAs($limited, 'web')
            ->post('/b2b/shipments/' . $shipment->id . '/issue')
            ->assertForbidden();
    }

    public function test_cross_tenant_completion_routes_remain_denied(): void
    {
        $userA = $this->createCompletionUser('organization', 'organization_owner');
        $userB = $this->createCompletionUser('organization', 'organization_owner');

        BillingWallet::factory()->funded(1000)->create([
            'account_id' => (string) $userB->account_id,
            'currency' => 'USD',
        ]);

        $shipmentB = $this->createDeclarationCompleteShipment($userB);

        $this->actingAs($userA, 'web')
            ->post('/b2b/shipments/' . $shipmentB->id . '/wallet-preflight')
            ->assertNotFound();

        $this->actingAs($userA, 'web')
            ->post('/b2b/shipments/' . $shipmentB->id . '/issue')
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function browserCompletionPersonaProvider(): array
    {
        return [
            'individual' => ['individual', 'individual', '/b2c/shipments/'],
            'organization_owner' => ['organization', 'organization_owner', '/b2b/shipments/'],
            'organization_admin' => ['organization', 'organization_admin', '/b2b/shipments/'],
            'staff' => ['organization', 'staff', '/b2b/shipments/'],
        ];
    }

    private function configureFedex(): void
    {
        config()->set('features.carrier_fedex', true);
        config()->set('services.fedex.client_id', 'fedex-test-client');
        config()->set('services.fedex.client_secret', 'fedex-test-secret');
        config()->set('services.fedex.account_number', '123456789');
        config()->set('services.fedex.base_url', 'https://apis-sandbox.fedex.com');
        config()->set('services.fedex.oauth_url', 'https://apis-base.test.cloud.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    private function createCompletionUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Completion ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Completion ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        return $this->createCompletionUserForAccount($account, $persona);
    }

    /**
     * @param array<int, string>|null $permissions
     */
    private function createCompletionUserForAccount(Account $account, string $persona, ?array $permissions = null): User
    {
        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, $permissions ?? [
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
        ], 'shipment_completion_web_' . $persona);

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
            'https://apis-base.test.cloud.fedex.com/oauth/token' => fn () => Http::response([
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
