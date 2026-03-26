<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\ContentDeclaration;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentPortalPolishWebTest extends TestCase
{
    public function test_arabic_shipment_page_localizes_service_status_timeline_and_document_metadata(): void
    {
        $user = $this->createPortalUser('organization_owner');
        $shipment = $this->createIssuedShipmentWithLocalizedArtifacts($user);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertOk()
            ->assertSee($this->ar('portal_shipments.services.fedex_ground'))
            ->assertDontSee('FEDEX_GROUND')
            ->assertSee($this->ar('portal_shipments.statuses.purchased'))
            ->assertDontSee('purchased')
            ->assertSee($this->ar('portal_shipments.events.shipment_purchased'))
            ->assertSee($this->ar('portal_shipments.events.carrier_documents_available'))
            ->assertDontSee('shipment.purchased')
            ->assertDontSee('carrier.documents_available')
            ->assertSeeInOrder(['الموقع', $this->ar('portal_shipments.carriers.fedex')])
            ->assertDontSee('>FEDEX<', false)
            ->assertSee($this->ar('portal_shipments.documents.types.label'))
            ->assertSee($this->ar('portal_shipments.carriers.fedex') . ' / ' . $this->ar('portal_shipments.documents.formats.pdf'))
            ->assertDontSee('fedex / pdf');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertOk()
            ->assertSee($this->ar('portal_shipments.documents.retrieval_modes.stored_object'))
            ->assertDontSee('stored_object')
            ->assertSee($this->ar('portal_shipments.carriers.fedex'))
            ->assertSee($this->ar('portal_shipments.documents.formats.pdf'));
    }

    public function test_arabic_offers_page_localizes_carrier_service_pair_and_service_name(): void
    {
        $user = $this->createPortalUser('organization_owner');
        $shipment = $this->createRatedShipmentWithLocalizedOffer($user);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/offers')
            ->assertOk()
            ->assertSee($this->ar('portal_shipments.carriers.fedex'))
            ->assertSee($this->ar('portal_shipments.services.fedex_ground'))
            ->assertSee($this->ar('portal_shipments.carriers.fedex') . ' / ' . $this->ar('portal_shipments.services.fedex_ground'))
            ->assertDontSee('fedex / FEDEX_GROUND')
            ->assertDontSee('FedEx Ground®');
    }

    public function test_arabic_declaration_page_localizes_selected_offer_labels(): void
    {
        $user = $this->createPortalUser('organization_owner');
        $shipment = $this->createDeclarationShipmentWithLocalizedOffer($user);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/declaration')
            ->assertOk()
            ->assertSee($this->ar('portal_shipments.carriers.fedex'))
            ->assertSee($this->ar('portal_shipments.services.fedex_ground'))
            ->assertDontSee('FedEx Ground®');
    }

    public function test_cross_tenant_external_404_routes_remain_not_found_and_render_branded_page(): void
    {
        $owner = $this->createPortalUser('organization_owner');
        $intruder = $this->createPortalUser('organization_owner');
        $shipment = $this->createIssuedShipmentWithLocalizedArtifacts($owner);

        $document = CarrierDocument::query()
            ->where('shipment_id', (string) $shipment->id)
            ->firstOrFail();

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertNotFound()
            ->assertSee($this->ar('portal_shipments.errors.external.404.heading'))
            ->assertDontSee('Not Found');

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertNotFound()
            ->assertSee($this->ar('portal_shipments.errors.external.404.heading'))
            ->assertDontSee('Not Found');

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents/' . $document->id . '/view/label_794700001234.pdf')
            ->assertNotFound()
            ->assertSee($this->ar('portal_shipments.errors.external.404.heading'))
            ->assertDontSee('Not Found');
    }

    public function test_same_tenant_missing_tracking_permission_gets_branded_403(): void
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Portal Polish ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $owner = $this->createPortalUserForAccount($account, 'organization_owner', [
            'shipments.read',
            'tracking.read',
        ]);
        $limited = $this->createPortalUserForAccount($account, 'staff_limited', [
            'shipments.read',
        ]);
        $shipment = $this->createIssuedShipmentWithLocalizedArtifacts($owner);

        $this->actingAs($limited, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertForbidden()
            ->assertSee($this->ar('portal_shipments.errors.external.403.heading'))
            ->assertDontSee('Forbidden');
    }

    private function createPortalUser(string $persona): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Portal Surface ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        return $this->createPortalUserForAccount($account, $persona, [
            'shipments.read',
            'tracking.read',
            'notifications.read',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ]);
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createPortalUserForAccount(Account $account, string $persona, array $permissions): User
    {
        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, $permissions, 'shipment_portal_polish_' . $persona);

        return $user;
    }

    private function createIssuedShipmentWithLocalizedArtifacts(User $user): Shipment
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'tracking_status' => 'purchased',
            'carrier_code' => 'fedex',
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
        ];

        if (Schema::hasColumn('shipments', 'service_code')) {
            $attributes['service_code'] = 'FEDEX_GROUND';
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $attributes['service_name'] = 'FedEx Ground';
        }

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $attributes['tracking_number'] = '794700001234';
        }

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $attributes['carrier_tracking_number'] = '794700001234';
        }

        $shipment = Shipment::factory()->create($attributes);

        $carrierShipment = CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'tracking_number' => '794700001234',
        ]);

        CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'type' => 'label',
            'format' => 'pdf',
            'retrieval_mode' => CarrierDocument::RETRIEVAL_STORED_OBJECT,
            'original_filename' => 'label_794700001234.pdf',
        ]);

        ShipmentEvent::factory()->purchased()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'shipment.purchased',
            'normalized_status' => 'purchased',
            'status' => 'purchased',
            'location' => 'FEDEX',
            'event_at' => now()->subMinutes(20),
        ]);

        ShipmentEvent::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'carrier.documents_available',
            'normalized_status' => 'label_ready',
            'status' => 'label_ready',
            'location' => 'FEDEX',
            'event_at' => now()->subMinutes(10),
        ]);

        return $shipment->fresh([
            'carrierShipment',
            'selectedRateOption',
            'rateQuote.selectedOption',
            'balanceReservation',
            'contentDeclaration.waiverVersion',
        ]);
    }

    private function createRatedShipmentWithLocalizedOffer(User $user): Shipment
    {
        $shipment = Shipment::factory()->create($this->localizedShipmentAttributes($user, Shipment::STATUS_RATED));

        [$quote] = $this->attachLocalizedFedexQuote($shipment, $user, false);

        $shipmentUpdates = [];

        if (Schema::hasColumn('shipments', 'rate_quote_id')) {
            $shipmentUpdates['rate_quote_id'] = (string) $quote->id;
        }

        if ($shipmentUpdates !== []) {
            $shipment->update($shipmentUpdates);
        }

        return $shipment->fresh(['rateQuote.options', 'selectedRateOption']);
    }

    private function createDeclarationShipmentWithLocalizedOffer(User $user): Shipment
    {
        $shipment = Shipment::factory()->create($this->localizedShipmentAttributes($user, Shipment::STATUS_DECLARATION_REQUIRED));

        [$quote, $option] = $this->attachLocalizedFedexQuote($shipment, $user, true);

        ContentDeclaration::query()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'contains_dangerous_goods' => false,
            'dg_flag_declared' => false,
            'status' => ContentDeclaration::STATUS_PENDING,
            'waiver_accepted' => false,
            'declared_by' => (string) $user->id,
            'locale' => 'ar',
        ]);

        $shipmentUpdates = [];

        if (Schema::hasColumn('shipments', 'rate_quote_id')) {
            $shipmentUpdates['rate_quote_id'] = (string) $quote->id;
        }

        if (Schema::hasColumn('shipments', 'selected_rate_option_id')) {
            $shipmentUpdates['selected_rate_option_id'] = (string) $option->id;
        }

        if ($shipmentUpdates !== []) {
            $shipment->update($shipmentUpdates);
        }

        return $shipment->fresh([
            'carrierShipment',
            'selectedRateOption',
            'rateQuote.selectedOption',
            'contentDeclaration.waiverVersion',
        ]);
    }

    /**
     * @return array{0: RateQuote, 1: RateOption}
     */
    private function attachLocalizedFedexQuote(Shipment $shipment, User $user, bool $selectOption): array
    {
        $quote = RateQuote::factory()->create([
            'account_id' => (string) $user->account_id,
            'shipment_id' => (string) $shipment->id,
            'origin_country' => 'US',
            'origin_city' => 'Memphis',
            'destination_country' => 'US',
            'destination_city' => 'Austin',
            'currency' => 'USD',
            'requested_by' => (string) $user->id,
            'status' => $selectOption ? RateQuote::STATUS_SELECTED : RateQuote::STATUS_COMPLETED,
            'options_count' => 1,
        ]);

        $option = RateOption::query()->create([
            'rate_quote_id' => (string) $quote->id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'fedex',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground®',
            'net_rate' => 60.00,
            'fuel_surcharge' => 10.00,
            'other_surcharges' => 0.00,
            'total_net_rate' => 70.00,
            'markup_amount' => 0.00,
            'service_fee' => 0.00,
            'retail_rate_before_rounding' => 70.00,
            'retail_rate' => 70.00,
            'profit_margin' => 0.00,
            'currency' => 'USD',
            'estimated_days_min' => 2,
            'estimated_days_max' => 2,
            'estimated_delivery_at' => now()->addDays(2),
            'pricing_breakdown' => ['stage' => 'retail'],
            'rule_evaluation_log' => ['pricing_path' => 'shipment_quote'],
            'is_available' => true,
        ]);

        if ($selectOption) {
            $quote->update([
                'selected_option_id' => (string) $option->id,
            ]);
        }

        return [$quote->fresh(), $option];
    }

    /**
     * @return array<string, mixed>
     */
    private function localizedShipmentAttributes(User $user, string $status): array
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => $status,
            'tracking_status' => 'unknown',
            'carrier_code' => 'fedex',
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
        ];

        if (Schema::hasColumn('shipments', 'service_code')) {
            $attributes['service_code'] = 'FEDEX_GROUND';
        }

        if (Schema::hasColumn('shipments', 'service_name')) {
            $attributes['service_name'] = 'FedEx Ground®';
        }

        return $attributes;
    }

    private function ar(string $key): string
    {
        return trans($key, [], 'ar');
    }
}
