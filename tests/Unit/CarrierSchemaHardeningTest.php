<?php

namespace Tests\Unit;

use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CarrierSchemaHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_carrier_shipment_requires_explicit_carrier_identity(): void
    {
        $shipment = Shipment::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        CarrierShipment::query()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $shipment->account_id,
            'idempotency_key' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }

    public function test_carrier_error_requires_explicit_carrier_code(): void
    {
        $shipment = Shipment::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        CarrierError::query()->create([
            'shipment_id' => (string) $shipment->id,
            'correlation_id' => (string) Str::uuid(),
            'operation' => CarrierError::OP_CREATE_SHIPMENT,
            'internal_code' => CarrierError::ERR_UNKNOWN,
            'internal_message' => 'Unknown carrier error',
        ]);
    }
}
