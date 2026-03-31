<?php

namespace Tests\Unit;

use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use InvalidArgumentException;
use Tests\TestCase;

class CarrierDocumentStorageContractTest extends TestCase
{
    public function test_inline_documents_cannot_claim_stored_object_fields(): void
    {
        [$shipment, $carrierShipment] = $this->createIssuedShipment();

        $this->expectException(InvalidArgumentException::class);

        CarrierDocument::query()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'type' => CarrierDocument::TYPE_LABEL,
            'format' => 'pdf',
            'mime_type' => 'application/pdf',
            'source' => CarrierDocument::SOURCE_CARRIER,
            'retrieval_mode' => CarrierDocument::RETRIEVAL_INLINE,
            'content_base64' => base64_encode('inline-binary'),
            'storage_disk' => 'local',
            'storage_path' => 'carrier-documents/invalid-inline.pdf',
            'is_available' => true,
        ]);
    }

    public function test_stored_object_documents_cannot_also_store_inline_payload(): void
    {
        [$shipment, $carrierShipment] = $this->createIssuedShipment();

        $this->expectException(InvalidArgumentException::class);

        CarrierDocument::query()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'type' => CarrierDocument::TYPE_LABEL,
            'format' => 'pdf',
            'mime_type' => 'application/pdf',
            'source' => CarrierDocument::SOURCE_CARRIER,
            'retrieval_mode' => CarrierDocument::RETRIEVAL_STORED_OBJECT,
            'content_base64' => base64_encode('inline-binary'),
            'storage_disk' => 'local',
            'storage_path' => 'carrier-documents/invalid-stored-object.pdf',
            'is_available' => true,
        ]);
    }

    public function test_documents_infer_inline_mode_from_inline_payload(): void
    {
        [$shipment, $carrierShipment] = $this->createIssuedShipment();

        $document = CarrierDocument::query()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'type' => CarrierDocument::TYPE_LABEL,
            'format' => 'pdf',
            'mime_type' => 'application/pdf',
            'source' => CarrierDocument::SOURCE_CARRIER,
            'content_base64' => base64_encode('inline-binary'),
            'is_available' => true,
        ]);

        $this->assertSame(CarrierDocument::RETRIEVAL_INLINE, $document->fresh()->resolvedRetrievalMode());
        $this->assertNull($document->fresh()->storage_disk);
    }

    /**
     * @return array{0: Shipment, 1: CarrierShipment}
     */
    private function createIssuedShipment(): array
    {
        $shipment = Shipment::factory()->create();
        $carrierShipment = CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $shipment->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
        ]);

        return [$shipment, $carrierShipment];
    }
}
