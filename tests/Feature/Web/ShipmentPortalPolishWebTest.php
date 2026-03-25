<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
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
            ->assertSee('فيدكس الأرضي')
            ->assertDontSee('FEDEX_GROUND')
            ->assertSee('تم إصدار الشحنة')
            ->assertDontSee('purchased')
            ->assertSee('تم إصدار الشحنة لدى الناقل')
            ->assertSee('أصبحت مستندات الشحنة متاحة')
            ->assertDontSee('shipment.purchased')
            ->assertDontSee('carrier.documents_available')
            ->assertSee('ملصق الشحنة')
            ->assertSee('فيدكس / PDF')
            ->assertDontSee('fedex / pdf');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertOk()
            ->assertSee('ملف محفوظ')
            ->assertDontSee('stored_object')
            ->assertSee('فيدكس')
            ->assertSee('PDF');
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
            ->assertSee('هذه الصفحة غير متاحة داخل البوابة الحالية')
            ->assertDontSee('Not Found');

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertNotFound()
            ->assertSee('هذه الصفحة غير متاحة داخل البوابة الحالية')
            ->assertDontSee('Not Found');

        $this->actingAs($intruder, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents/' . $document->id . '/view/label_794700001234.pdf')
            ->assertNotFound()
            ->assertSee('هذه الصفحة غير متاحة داخل البوابة الحالية')
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
            ->assertSee('لا يمكنك فتح هذه الصفحة من هذا الحساب')
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
            'event_at' => now()->subMinutes(20),
        ]);

        ShipmentEvent::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'carrier.documents_available',
            'normalized_status' => 'label_ready',
            'status' => 'label_ready',
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
}
