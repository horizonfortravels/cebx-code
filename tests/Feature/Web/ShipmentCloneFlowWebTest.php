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
use App\Models\WalletHold;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentCloneFlowWebTest extends TestCase
{
    #[DataProvider('portalProvider')]
    public function test_clone_actions_are_visible_on_index_and_detail_surfaces(
        string $accountType,
        string $persona,
        string $indexRouteName,
        string $createRouteName,
        string $showRouteName,
        string $storeRouteName
    ): void {
        $user = $this->createCloneUser($accountType, $persona);
        $shipment = $this->createCloneableShipment($user, [
            'reference_number' => 'CLONE-SURFACE-' . Str::upper(Str::random(6)),
            'status' => Shipment::STATUS_DRAFT,
        ]);

        $cloneHref = route($createRouteName, ['clone' => (string) $shipment->id]);

        $this->actingAs($user, 'web')
            ->get(route($indexRouteName))
            ->assertOk()
            ->assertSee($cloneHref, false)
            ->assertSee('data-testid="shipment-clone-link-' . $shipment->id . '"', false);

        $this->actingAs($user, 'web')
            ->get(route($showRouteName, ['id' => (string) $shipment->id]))
            ->assertOk()
            ->assertSee($cloneHref, false)
            ->assertSee('data-testid="shipment-clone-primary"', false);
    }

    #[DataProvider('portalProvider')]
    public function test_clone_flow_still_works_from_filtered_portal_list(
        string $accountType,
        string $persona,
        string $indexRouteName,
        string $createRouteName,
        string $showRouteName,
        string $storeRouteName
    ): void {
        $user = $this->createCloneUser($accountType, $persona . '_filtered');
        $shipment = $this->createCloneableShipment($user, [
            'reference_number' => 'FILTER-CLONE-' . Str::upper(Str::random(6)),
            'status' => Shipment::STATUS_DRAFT,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
        ]);

        $this->createCloneableShipment($user, [
            'reference_number' => 'FILTER-HIDDEN-' . Str::upper(Str::random(6)),
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'dhl',
            'carrier_name' => 'DHL',
        ]);

        $cloneHref = route($createRouteName, ['clone' => (string) $shipment->id]);

        $this->actingAs($user, 'web')
            ->get(route($indexRouteName, [
                'search' => 'FILTER-CLONE',
                'status' => Shipment::STATUS_DRAFT,
                'carrier' => 'fedex',
            ]))
            ->assertOk()
            ->assertSee($shipment->reference_number)
            ->assertSee($cloneHref, false)
            ->assertSee('data-testid="shipment-clone-link-' . $shipment->id . '"', false)
            ->assertDontSee('FILTER-HIDDEN-');

        $this->actingAs($user, 'web')
            ->get($cloneHref)
            ->assertOk()
            ->assertSee('data-testid="clone-prefill-banner"', false)
            ->assertSee($shipment->reference_number);
    }

    #[DataProvider('portalProvider')]
    public function test_clone_create_page_prefills_only_safe_visible_fields_and_flags_multi_parcel_limit(
        string $accountType,
        string $persona,
        string $indexRouteName,
        string $createRouteName,
        string $showRouteName,
        string $storeRouteName
    ): void {
        $user = $this->createCloneUser($accountType, $persona);
        $shipment = $this->createCloneableShipment($user, [
            'reference_number' => 'CLONE-PREFILL-' . Str::upper(Str::random(6)),
            'sender_name' => 'Clone Sender',
            'sender_phone' => '+14015550001',
            'sender_address_1' => '100 Alpha St',
            'sender_city' => 'Providence',
            'sender_state' => 'ri',
            'sender_postal_code' => '02903',
            'sender_country' => 'us',
            'recipient_name' => 'Clone Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address_1' => '200 Beta Ave',
            'recipient_city' => 'New York',
            'recipient_state' => 'ny',
            'recipient_postal_code' => '10001',
            'recipient_country' => 'us',
            'status' => Shipment::STATUS_PURCHASED,
            'tracking_number' => 'TRACK-CLONE-PREFILL',
        ]);

        $this->replaceShipmentParcels($shipment, [
            [
                'sequence' => 1,
                'weight' => '2.500',
                'length' => '30.00',
                'width' => '20.00',
                'height' => '10.00',
            ],
            [
                'sequence' => 2,
                'weight' => '9.900',
                'length' => '90.00',
                'width' => '80.00',
                'height' => '70.00',
            ],
        ]);

        $this->actingAs($user, 'web')
            ->get(route($createRouteName, ['clone' => (string) $shipment->id]))
            ->assertOk()
            ->assertSee('data-testid="clone-prefill-banner"', false)
            ->assertSee($shipment->reference_number)
            ->assertSee(__('portal_shipments.clone.first_parcel_only'))
            ->assertSee('value="Clone Sender"', false)
            ->assertSee('value="+14015550001"', false)
            ->assertSee('value="100 Alpha St"', false)
            ->assertSee('value="Providence"', false)
            ->assertSee('value="RI"', false)
            ->assertSee('value="02903"', false)
            ->assertSee('value="US"', false)
            ->assertSee('value="Clone Recipient"', false)
            ->assertSee('value="+12025550123"', false)
            ->assertSee('value="200 Beta Ave"', false)
            ->assertSee('value="New York"', false)
            ->assertSee('value="NY"', false)
            ->assertSee('value="10001"', false)
            ->assertSee('value="2.500"', false)
            ->assertSee('value="30.00"', false)
            ->assertSee('value="20.00"', false)
            ->assertSee('value="10.00"', false)
            ->assertDontSee('value="9.900"', false);
    }

    #[DataProvider('portalProvider')]
    public function test_submitting_cloned_values_creates_a_fresh_shipment_without_copying_system_owned_fields(
        string $accountType,
        string $persona,
        string $indexRouteName,
        string $createRouteName,
        string $showRouteName,
        string $storeRouteName
    ): void {
        $user = $this->createCloneUser($accountType, $persona);
        $shipment = $this->createCloneableShipment($user, [
            'reference_number' => 'CLONE-SOURCE-' . Str::upper(Str::random(6)),
            'sender_name' => 'Source Sender',
            'sender_phone' => '+14015550001',
            'sender_address_1' => '10 Source Rd',
            'sender_city' => 'Providence',
            'sender_state' => 'RI',
            'sender_postal_code' => '02903',
            'sender_country' => 'US',
            'recipient_name' => 'Source Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address_1' => '20 Receiver Ave',
            'recipient_city' => 'Dallas',
            'recipient_state' => 'TX',
            'recipient_postal_code' => '75001',
            'recipient_country' => 'US',
            'status' => Shipment::STATUS_PURCHASED,
            'tracking_number' => 'TRACK-CLONE-SOURCE',
            'carrier_tracking_number' => 'CARRIER-CLONE-SOURCE',
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'total_charge' => 88.50,
            'currency' => 'USD',
        ]);

        $this->replaceShipmentParcels($shipment, [
            [
                'sequence' => 1,
                'weight' => '4.250',
                'length' => '25.00',
                'width' => '15.00',
                'height' => '12.00',
            ],
            [
                'sequence' => 2,
                'weight' => '8.750',
                'length' => '55.00',
                'width' => '40.00',
                'height' => '35.00',
            ],
        ]);

        [$quote, $option] = $this->attachCloneSourceQuote($shipment, $user);
        $hold = $this->attachCloneSourceWalletHold($shipment, $user);
        $carrierShipment = $this->attachCloneSourceCarrierShipment($shipment, $user);
        $this->attachCloneSourceDeclaration($shipment);

        $shipment->forceFill(array_filter([
            'rate_quote_id' => Schema::hasColumn('shipments', 'rate_quote_id') ? (string) $quote->id : null,
            'selected_rate_option_id' => Schema::hasColumn('shipments', 'selected_rate_option_id') ? (string) $option->id : null,
            'balance_reservation_id' => Schema::hasColumn('shipments', 'balance_reservation_id') ? (string) $hold->id : null,
            'carrier_shipment_id' => Schema::hasColumn('shipments', 'carrier_shipment_id') ? 'carrier-shipment-source' : null,
        ], static fn ($value) => $value !== null))->save();

        $payload = $this->shipmentPayloadFromSource($shipment);

        $response = $this->actingAs($user, 'web')
            ->from(route($createRouteName, ['clone' => (string) $shipment->id]))
            ->post(route($storeRouteName), $payload);

        $response->assertRedirect();

        /** @var Shipment $newShipment */
        $newShipment = Shipment::query()
            ->where('account_id', (string) $user->account_id)
            ->where('id', '!=', (string) $shipment->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertStringContainsString(
            route($createRouteName, ['draft' => (string) $newShipment->id], false),
            (string) $response->headers->get('Location')
        );

        $newShipment->refresh()->load(['parcels', 'carrierShipment', 'contentDeclaration']);
        $shipment->refresh()->load(['parcels', 'carrierShipment', 'contentDeclaration']);

        $this->assertNotSame((string) $shipment->id, (string) $newShipment->id);
        $this->assertNotSame((string) $shipment->reference_number, (string) $newShipment->reference_number);
        $this->assertSame(Shipment::STATUS_READY_FOR_RATES, (string) $newShipment->status);
        $this->assertSame((string) $shipment->sender_name, (string) $newShipment->sender_name);
        $this->assertSame((string) $shipment->sender_phone, (string) $newShipment->sender_phone);
        $this->assertSame((string) $shipment->sender_address_1, (string) $newShipment->sender_address_1);
        $this->assertSame((string) $shipment->sender_city, (string) $newShipment->sender_city);
        $this->assertSame((string) $shipment->sender_state, (string) $newShipment->sender_state);
        $this->assertSame((string) $shipment->sender_postal_code, (string) $newShipment->sender_postal_code);
        $this->assertSame((string) $shipment->sender_country, (string) $newShipment->sender_country);
        $this->assertSame((string) $shipment->recipient_name, (string) $newShipment->recipient_name);
        $this->assertSame((string) $shipment->recipient_phone, (string) $newShipment->recipient_phone);
        $this->assertSame((string) $shipment->recipient_address_1, (string) $newShipment->recipient_address_1);
        $this->assertSame((string) $shipment->recipient_city, (string) $newShipment->recipient_city);
        $this->assertSame((string) $shipment->recipient_state, (string) $newShipment->recipient_state);
        $this->assertSame((string) $shipment->recipient_postal_code, (string) $newShipment->recipient_postal_code);
        $this->assertSame((string) $shipment->recipient_country, (string) $newShipment->recipient_country);
        $this->assertCount(1, $newShipment->parcels);
        $this->assertSame('4.250', (string) $newShipment->parcels->firstOrFail()->weight);
        $this->assertSame('25.00', (string) $newShipment->parcels->firstOrFail()->length);
        $this->assertSame('15.00', (string) $newShipment->parcels->firstOrFail()->width);
        $this->assertSame('12.00', (string) $newShipment->parcels->firstOrFail()->height);
        $this->assertNull($newShipment->tracking_number);
        $this->assertNull($newShipment->carrierShipment);
        $this->assertNull($newShipment->contentDeclaration);

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $this->assertNull($newShipment->carrier_tracking_number);
        }
        if (Schema::hasColumn('shipments', 'rate_quote_id')) {
            $this->assertNull($newShipment->rate_quote_id);
        }
        if (Schema::hasColumn('shipments', 'selected_rate_option_id')) {
            $this->assertNull($newShipment->selected_rate_option_id);
        }
        if (Schema::hasColumn('shipments', 'balance_reservation_id')) {
            $this->assertNull($newShipment->balance_reservation_id);
        }
        if (Schema::hasColumn('shipments', 'carrier_shipment_id')) {
            $this->assertNull($newShipment->carrier_shipment_id);
        }

        $this->assertSame(Shipment::STATUS_PURCHASED, (string) $shipment->status);
        $this->assertSame((string) $carrierShipment->id, (string) $shipment->carrierShipment?->id);
        $this->assertSame('TRACK-CLONE-SOURCE', (string) $shipment->carrierShipment?->tracking_number);
        $this->assertNotNull($shipment->contentDeclaration);
        $this->assertCount(2, $shipment->parcels);

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $this->assertSame('CARRIER-CLONE-SOURCE', (string) $shipment->carrier_tracking_number);
        }
    }

    #[DataProvider('portalProvider')]
    public function test_cross_tenant_clone_attempt_returns_not_found(
        string $accountType,
        string $persona,
        string $indexRouteName,
        string $createRouteName,
        string $showRouteName,
        string $storeRouteName
    ): void {
        $viewer = $this->createCloneUser($accountType, $persona);
        $other = $this->createCloneUser($accountType, $persona . '_other');
        $shipment = $this->createCloneableShipment($other, [
            'reference_number' => 'CLONE-XTEN-' . Str::upper(Str::random(6)),
        ]);

        $this->actingAs($viewer, 'web')
            ->get(route($createRouteName, ['clone' => (string) $shipment->id]))
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    public static function portalProvider(): array
    {
        return [
            'b2c' => ['individual', 'individual', 'b2c.shipments.index', 'b2c.shipments.create', 'b2c.shipments.show', 'b2c.shipments.store'],
            'b2b' => ['organization', 'organization_owner', 'b2b.shipments.index', 'b2b.shipments.create', 'b2b.shipments.show', 'b2b.shipments.store'],
        ];
    }

    private function createCloneUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'Clone B2C ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'Clone B2B ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.read',
            'shipments.create',
            'shipments.update_draft',
            'tracking.read',
            'rates.read',
            'quotes.read',
            'quotes.manage',
            'dg.read',
            'dg.manage',
            'billing.view',
            'billing.manage',
        ], 'shipment_clone_' . $persona . '_' . $accountType);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCloneableShipment(User $user, array $attributes = []): Shipment
    {
        return Shipment::factory()->create($this->filterShipmentColumns(array_merge([
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'created_by' => (string) $user->id,
            'status' => Shipment::STATUS_DRAFT,
            'sender_name' => 'Sender',
            'sender_phone' => '+14015550001',
            'sender_address' => '1 Sender St',
            'sender_address_1' => '1 Sender St',
            'sender_city' => 'Providence',
            'sender_state' => 'RI',
            'sender_postal_code' => '02903',
            'sender_country' => 'US',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address' => '2 Recipient Ave',
            'recipient_address_1' => '2 Recipient Ave',
            'recipient_city' => 'Dallas',
            'recipient_state' => 'TX',
            'recipient_postal_code' => '75001',
            'recipient_country' => 'US',
            'is_international' => false,
            'parcels_count' => 1,
            'pieces' => 1,
            'total_weight' => 1,
            'weight' => 1,
            'chargeable_weight' => 1,
            'currency' => 'USD',
        ], $attributes)));
    }

    /**
     * @param array<int, array{sequence: int, weight: string, length: string, width: string, height: string}> $parcels
     */
    private function replaceShipmentParcels(Shipment $shipment, array $parcels): void
    {
        $shipment->parcels()->delete();

        foreach ($parcels as $parcel) {
            Parcel::factory()->create([
                'shipment_id' => (string) $shipment->id,
                'sequence' => $parcel['sequence'],
                'weight' => $parcel['weight'],
                'length' => $parcel['length'],
                'width' => $parcel['width'],
                'height' => $parcel['height'],
            ]);
        }

        $shipment->refresh();
    }

    /**
     * @return array{0: RateQuote, 1: RateOption}
     */
    private function attachCloneSourceQuote(Shipment $shipment, User $user): array
    {
        $quote = RateQuote::factory()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'origin_country' => (string) $shipment->sender_country,
            'origin_city' => (string) $shipment->sender_city,
            'destination_country' => (string) $shipment->recipient_country,
            'destination_city' => (string) $shipment->recipient_city,
            'currency' => (string) ($shipment->currency ?? 'USD'),
            'requested_by' => (string) $user->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        $option = RateOption::query()->create([
            'rate_quote_id' => (string) $quote->id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'net_rate' => 80.00,
            'fuel_surcharge' => 8.50,
            'other_surcharges' => 0.00,
            'total_net_rate' => 88.50,
            'markup_amount' => 0.00,
            'service_fee' => 0.00,
            'retail_rate_before_rounding' => 88.50,
            'retail_rate' => 88.50,
            'profit_margin' => 0.00,
            'currency' => 'USD',
            'estimated_days_min' => 2,
            'estimated_days_max' => 2,
            'estimated_delivery_at' => now()->addDays(2),
            'pricing_breakdown' => ['stage' => 'retail'],
            'rule_evaluation_log' => ['pricing_path' => 'clone_test'],
            'is_available' => true,
        ]);

        $quote->update([
            'selected_option_id' => (string) $option->id,
            'status' => RateQuote::STATUS_SELECTED,
        ]);

        return [$quote, $option];
    }

    private function attachCloneSourceWalletHold(Shipment $shipment, User $user): WalletHold
    {
        $wallet = BillingWallet::factory()->funded(500)->create([
            'account_id' => (string) $user->account_id,
            'currency' => 'USD',
        ]);

        return WalletHold::query()->create([
            'wallet_id' => (string) $wallet->id,
            'account_id' => (string) $user->account_id,
            'amount' => 88.50,
            'currency' => 'USD',
            'shipment_id' => (string) $shipment->id,
            'source' => 'shipment_issue',
            'status' => WalletHold::STATUS_ACTIVE,
            'idempotency_key' => 'clone-source-wallet-' . Str::lower(Str::random(8)),
            'actor_id' => (string) $user->id,
        ]);
    }

    private function attachCloneSourceCarrierShipment(Shipment $shipment, User $user): CarrierShipment
    {
        return CarrierShipment::query()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'carrier_shipment_id' => 'fedex-clone-source',
            'tracking_number' => 'TRACK-CLONE-SOURCE',
            'awb_number' => 'TRACK-CLONE-SOURCE',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'status' => CarrierShipment::STATUS_LABEL_READY,
            'idempotency_key' => 'carrier-clone-source-' . Str::lower(Str::random(8)),
            'attempt_count' => 1,
            'correlation_id' => 'carrier-clone-source-' . Str::lower(Str::random(12)),
        ]);
    }

    private function attachCloneSourceDeclaration(Shipment $shipment): void
    {
        ContentDeclaration::query()->create([
            'account_id' => (string) $shipment->account_id,
            'shipment_id' => (string) $shipment->id,
            'contains_dangerous_goods' => false,
            'dg_flag_declared' => true,
            'status' => ContentDeclaration::STATUS_COMPLETED,
            'waiver_accepted' => true,
            'declared_by' => (string) $shipment->user_id,
            'ip_address' => '127.0.0.1',
            'locale' => 'ar',
            'declared_at' => now(),
            'waiver_accepted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentPayloadFromSource(Shipment $shipment): array
    {
        $firstParcel = $shipment->parcels()->orderBy('sequence')->firstOrFail();

        return [
            'sender_name' => (string) $shipment->sender_name,
            'sender_phone' => (string) $shipment->sender_phone,
            'sender_address_1' => (string) $shipment->sender_address_1,
            'sender_city' => (string) $shipment->sender_city,
            'sender_state' => (string) $shipment->sender_state,
            'sender_postal_code' => (string) $shipment->sender_postal_code,
            'sender_country' => (string) $shipment->sender_country,
            'recipient_name' => (string) $shipment->recipient_name,
            'recipient_phone' => (string) $shipment->recipient_phone,
            'recipient_address_1' => (string) $shipment->recipient_address_1,
            'recipient_city' => (string) $shipment->recipient_city,
            'recipient_state' => (string) $shipment->recipient_state,
            'recipient_postal_code' => (string) $shipment->recipient_postal_code,
            'recipient_country' => (string) $shipment->recipient_country,
            'parcels' => [[
                'weight' => (float) $firstParcel->weight,
                'length' => (float) $firstParcel->length,
                'width' => (float) $firstParcel->width,
                'height' => (float) $firstParcel->height,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterShipmentColumns(array $attributes): array
    {
        if (! Schema::hasTable('shipments')) {
            return $attributes;
        }

        $filtered = [];
        foreach ($attributes as $column => $value) {
            if (Schema::hasColumn('shipments', $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }
}
