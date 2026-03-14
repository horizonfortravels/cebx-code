<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\Parcel;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Carriers\DhlApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * API Tests — CR Module (FR-CR-001→008)
 *
 * 20 test methods covering all carrier integration endpoints.
 */
class CarrierApiTest extends TestCase
{
    use RefreshDatabase;

    private $dhlApiMock;
    private Account $account;
    private User $owner;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dhlApiMock = Mockery::mock(DhlApiService::class);
        $this->app->instance(DhlApiService::class, $this->dhlApiMock);

        $this->account = Account::factory()->create();
        $ownerRole = Role::factory()->create([
            'account_id' => $this->account->id,
            'slug'       => 'owner',
        ]);
        $memberRole = Role::factory()->create([
            'account_id' => $this->account->id,
            'slug'       => 'member',
        ]);

        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $ownerRole->id,
        ]);
        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $memberRole->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /shipments/{id}/carrier/create — FR-CR-001
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_at_carrier_success(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/create", [
                'label_format' => 'pdf',
                'label_size'   => '4x6',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status', 'message',
                'data' => ['carrier_shipment_id', 'tracking_number', 'status', 'carrier'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_at_carrier_validates_format(): void
    {
        $shipment = $this->createReadyShipment();

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/create", [
                'label_format' => 'invalid_format',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_at_carrier_returns_documents(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/create");

        $response->assertStatus(201)
            ->assertJsonPath('data.tracking_number', '1234567890');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_at_carrier_handles_failure(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andThrow(new \Exception('DHL error', 500));

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/create");

        $response->assertStatus(502);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /shipments/{id}/carrier/refetch — FR-CR-005
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_refetch_label(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_READY_FOR_PICKUP,
        ]);

        CarrierShipment::factory()->created()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->dhlApiMock->shouldReceive('fetchLabel')
            ->once()
            ->andReturn([
                'content' => base64_encode('new label content'),
                'format'  => 'pdf',
                'url'     => null,
            ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/refetch");

        $response->assertOk()
            ->assertJsonPath('data.type', 'label');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_refetch_with_zpl_format(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_READY_FOR_PICKUP,
        ]);

        CarrierShipment::factory()->created()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->dhlApiMock->shouldReceive('fetchLabel')
            ->once()
            ->andReturn([
                'content' => base64_encode('^XA^FDTest^FS^XZ'),
                'format'  => 'zpl',
                'url'     => null,
            ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/refetch", [
                'format' => 'zpl',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'zpl');
    }

    // ═══════════════════════════════════════════════════════════
    // POST /shipments/{id}/carrier/cancel — FR-CR-006
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_cancel_at_carrier(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_READY_FOR_PICKUP,
        ]);

        CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->dhlApiMock->shouldReceive('cancelShipment')
            ->once()
            ->andReturn(['cancellationId' => 'CXL-456']);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/cancel", [
                'reason' => 'Changed my mind',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.carrier_status', 'cancelled');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_cancel_rejects_non_cancellable(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
        ]);

        CarrierShipment::factory()->cancelled()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/cancel");

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /shipments/{id}/carrier/retry — FR-CR-003
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_retry_failed_creation(): void
    {
        $shipment = $this->createReadyShipment();

        CarrierShipment::factory()->failed()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$shipment->id}/carrier/retry");

        $response->assertOk()
            ->assertJsonPath('data.tracking_number', '1234567890');
    }

    // ═══════════════════════════════════════════════════════════
    // GET /shipments/{id}/carrier/status
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_carrier_status(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
        ]);

        CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
            'tracking_number' => 'TRK-789',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$shipment->id}/carrier/status");

        $response->assertOk()
            ->assertJsonPath('data.tracking_number', 'TRK-789')
            ->assertJsonPath('data.status', 'label_ready');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_carrier_status_null_when_no_record(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$shipment->id}/carrier/status");

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /shipments/{id}/documents — FR-CR-008
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_documents(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $cs = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        CarrierDocument::factory()->count(2)->create([
            'carrier_shipment_id' => $cs->id,
            'shipment_id'         => $shipment->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$shipment->id}/documents");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ═══════════════════════════════════════════════════════════
    // GET /shipments/{id}/documents/{docId} — FR-CR-008
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_download_document(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $cs = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $doc = CarrierDocument::factory()->create([
            'carrier_shipment_id' => $cs->id,
            'shipment_id'         => $shipment->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/api/v1/shipments/{$shipment->id}/documents/{$doc->id}");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_download_returns_no_financial_data(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $cs = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $doc = CarrierDocument::factory()->create([
            'carrier_shipment_id' => $cs->id,
            'shipment_id'         => $shipment->id,
        ]);

        $response = $this->actingAs($this->member)
            ->get("/api/v1/shipments/{$shipment->id}/documents/{$doc->id}");

        // Response should be binary content, not JSON with financial data
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('net_rate', $content);
        $this->assertStringNotContainsString('retail_rate', $content);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_download_unavailable_doc_returns_error(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $cs = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $doc = CarrierDocument::factory()->unavailable()->create([
            'carrier_shipment_id' => $cs->id,
            'shipment_id'         => $shipment->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/api/v1/shipments/{$shipment->id}/documents/{$doc->id}");

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /shipments/{id}/carrier/errors — FR-CR-004
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_carrier_errors(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);

        CarrierError::factory()->count(2)->create([
            'shipment_id' => $shipment->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$shipment->id}/carrier/errors");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_errors_include_retriable_flag(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);

        CarrierError::factory()->retriable()->create([
            'shipment_id' => $shipment->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$shipment->id}/carrier/errors");

        $response->assertOk()
            ->assertJsonPath('data.0.is_retriable', true);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function createReadyShipment(): Shipment
    {
        $shipment = Shipment::factory()->create([
            'account_id'          => $this->account->id,
            'status'              => Shipment::STATUS_PURCHASED,
            'sender_name'         => 'Test Sender',
            'sender_phone'        => '+966501234567',
            'sender_city'         => 'Riyadh',
            'sender_country'      => 'SA',
            'sender_postal_code'  => '12345',
            'sender_address_line1' => '123 Main St',
            'recipient_name'      => 'Test Recipient',
            'recipient_phone'     => '+971501234567',
            'recipient_city'      => 'Dubai',
            'recipient_country'   => 'AE',
            'recipient_postal_code' => '00000',
            'recipient_address_line1' => '456 Other St',
        ]);

        Parcel::factory()->create([
            'shipment_id' => $shipment->id,
            'weight'      => 2.5,
            'length'      => 30,
            'width'       => 20,
            'height'      => 15,
        ]);

        return $shipment;
    }

    private function fakeDhlCreateResponse(): array
    {
        return [
            'shipmentId'                  => '1234567890',
            'trackingNumber'              => '1234567890',
            'dispatchConfirmationNumber'  => 'DCN-123',
            'serviceCode'                 => 'P',
            'serviceName'                 => 'DHL Express Worldwide',
            'productCode'                 => 'P',
            'cancellable'                 => true,
            'documents'                   => [
                [
                    'type'    => 'label',
                    'format'  => 'pdf',
                    'content' => base64_encode('fake PDF label content for testing'),
                ],
            ],
        ];
    }
}
