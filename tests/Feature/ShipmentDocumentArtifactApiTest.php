<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentDocumentArtifactApiTest extends TestCase
{
    public function test_same_tenant_user_can_list_shipment_documents(): void
    {
        $user = $this->createDocumentActor(['shipments.read']);
        [$shipment, $carrierShipment] = $this->createIssuedShipmentWithCarrierRecord($user);

        CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'type' => CarrierDocument::TYPE_LABEL,
            'retrieval_mode' => CarrierDocument::RETRIEVAL_STORED_OBJECT,
        ]);

        CarrierDocument::factory()->withDownloadUrl()->commercialInvoice()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
        ]);

        $this->getJson('/api/v1/shipments/' . $shipment->id . '/documents', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.carrier_code', 'fedex');
    }

    public function test_same_tenant_missing_permission_gets_403_for_document_listing(): void
    {
        $user = $this->createDocumentActor([]);
        [$shipment] = $this->createIssuedShipmentWithCarrierRecord($user);

        $this->getJson('/api/v1/shipments/' . $shipment->id . '/documents', $this->authHeaders($user))
            ->assertForbidden();
    }

    public function test_cross_tenant_document_access_returns_404(): void
    {
        $owner = $this->createDocumentActor(['shipments.read']);
        $other = $this->createDocumentActor(['shipments.read']);
        [$shipment] = $this->createIssuedShipmentWithCarrierRecord($other);

        $this->getJson('/api/v1/shipments/' . $shipment->id . '/documents', $this->authHeaders($owner))
            ->assertNotFound();
    }

    public function test_same_tenant_user_can_download_stored_document(): void
    {
        Storage::fake('local');

        $user = $this->createDocumentActor(['shipments.read']);
        [$shipment, $carrierShipment] = $this->createIssuedShipmentWithCarrierRecord($user);
        $path = 'carrier-documents/' . $shipment->id . '/' . $carrierShipment->id . '/label.pdf';
        Storage::disk('local')->put($path, 'fake-label-binary');

        $document = CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'format' => 'pdf',
            'mime_type' => 'application/pdf',
            'retrieval_mode' => CarrierDocument::RETRIEVAL_STORED_OBJECT,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'content_base64' => null,
            'file_size' => strlen('fake-label-binary'),
            'checksum' => hash('sha256', 'fake-label-binary'),
        ]);

        $this->get('/api/v1/shipments/' . $shipment->id . '/documents/' . $document->id, $this->authHeaders($user))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Checksum-SHA256', hash('sha256', 'fake-label-binary'));
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createDocumentActor(array $permissions): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Docs Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        if ($permissions !== []) {
            $this->grantTenantPermissions($user, $permissions, 'shipment_document_reader');
        }

        return $user;
    }

    /**
     * @return array{0: Shipment, 1: CarrierShipment}
     */
    private function createIssuedShipmentWithCarrierRecord(User $user): array
    {
        $shipment = Shipment::factory()->create([
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
        ]);

        $carrierShipment = CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
        ]);

        return [$shipment, $carrierShipment];
    }
}
