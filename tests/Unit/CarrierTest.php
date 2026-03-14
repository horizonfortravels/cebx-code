<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierError;
use App\Models\CarrierShipment;
use App\Models\Parcel;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CarrierService;
use App\Services\Carriers\DhlApiService;
use App\Services\WalletBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit Tests — CR Module (FR-CR-001→008)
 *
 * 45 test methods covering all 8 functional requirements.
 */
class CarrierTest extends TestCase
{
    use RefreshDatabase;

    private CarrierService $service;
    private $dhlApiMock;
    private Account $account;
    private User $owner;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dhlApiMock = Mockery::mock(DhlApiService::class);
        $this->app->instance(DhlApiService::class, $this->dhlApiMock);

        $this->service = $this->app->make(CarrierService::class);

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
    // FR-CR-001: Create Shipment at Carrier (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_at_carrier_returns_tracking_number(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertNotNull($carrierShipment->tracking_number);
        $this->assertEquals('1234567890', $carrierShipment->tracking_number);
        $this->assertEquals('dhl', $carrierShipment->carrier_code);
        $this->assertNotNull($carrierShipment->carrier_shipment_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_at_carrier_stores_carrier_reference(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals('1234567890', $carrierShipment->carrier_shipment_id);
        $this->assertEquals('1234567890', $carrierShipment->awb_number);
        $this->assertNotNull($carrierShipment->correlation_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_at_carrier_updates_shipment_status(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $this->service->createAtCarrier($shipment, $this->owner);

        $shipment->refresh();
        $this->assertEquals(Shipment::STATUS_READY_FOR_PICKUP, $shipment->status);
        $this->assertEquals('1234567890', $shipment->tracking_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_at_carrier_rejects_non_purchased_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_DRAFT,
        ]);
        Parcel::factory()->create(['shipment_id' => $shipment->id]);

        $this->expectException(BusinessException::class);
        $this->service->createAtCarrier($shipment, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_at_carrier_rejects_missing_parcels(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id'     => $this->account->id,
            'status'         => Shipment::STATUS_PURCHASED,
            'sender_name'    => 'Test Sender',
            'recipient_name' => 'Test Recipient',
        ]);
        // No parcels

        $this->expectException(BusinessException::class);
        $this->service->createAtCarrier($shipment, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-002: Receive & Store Label/Docs (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_label_stored_after_carrier_creation(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals(CarrierShipment::STATUS_LABEL_READY, $carrierShipment->status);
        $this->assertTrue($carrierShipment->documents()->exists());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_label_document_has_correct_metadata(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);
        $label = $carrierShipment->documents()->where('type', 'label')->first();

        $this->assertNotNull($label);
        $this->assertEquals('pdf', $label->format);
        $this->assertEquals('application/pdf', $label->mime_type);
        $this->assertTrue($label->is_available);
        $this->assertNotNull($label->checksum);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_multiple_documents_stored(): void
    {
        $shipment = $this->createReadyShipment();

        $response = $this->fakeDhlCreateResponse();
        $response['documents'][] = [
            'type'    => 'invoice',
            'format'  => 'pdf',
            'content' => base64_encode('fake invoice PDF content'),
        ];

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($response);

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals(2, $carrierShipment->documents()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_label_pending_when_no_documents_returned(): void
    {
        $shipment = $this->createReadyShipment();

        $response = $this->fakeDhlCreateResponse();
        $response['documents'] = []; // No docs

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($response);

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals(CarrierShipment::STATUS_LABEL_PENDING, $carrierShipment->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_document_content_decoded_correctly(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $carrierShipment = $this->service->createAtCarrier($shipment, $this->owner);
        $label = $carrierShipment->documents()->first();

        $decoded = $label->getDecodedContent();
        $this->assertNotNull($decoded);
        $this->assertNotEmpty($decoded);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-003: Idempotency (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotency_key_generated_consistently(): void
    {
        $shipmentId = 'test-shipment-id';
        $key1 = CarrierShipment::generateIdempotencyKey($shipmentId);
        $key2 = CarrierShipment::generateIdempotencyKey($shipmentId);

        $this->assertEquals($key1, $key2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotency_key_differs_for_different_shipments(): void
    {
        $key1 = CarrierShipment::generateIdempotencyKey('shipment-1');
        $key2 = CarrierShipment::generateIdempotencyKey('shipment-2');

        $this->assertNotEquals($key1, $key2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_duplicate_creation_returns_same_result(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once() // Only called ONCE (idempotent)
            ->andReturn($this->fakeDhlCreateResponse());

        // First call creates
        $result1 = $this->service->createAtCarrier($shipment, $this->owner);

        // Second call returns existing (idempotent)
        $result2 = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->tracking_number, $result2->tracking_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotency_key_unique_constraint(): void
    {
        $key = 'test-unique-key';

        CarrierShipment::factory()->create([
            'account_id'      => $this->account->id,
            'idempotency_key' => $key,
        ]);

        $this->expectException(\Exception::class);
        CarrierShipment::factory()->create([
            'account_id'      => $this->account->id,
            'idempotency_key' => $key,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_retry_uses_different_idempotency_flow(): void
    {
        $shipment = $this->createReadyShipment();

        // First attempt fails
        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andThrow(new \Exception('DHL error', 500));

        try {
            $this->service->createAtCarrier($shipment, $this->owner);
        } catch (BusinessException $e) {}

        // Retry should work with new attempt
        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $shipment->update(['status' => Shipment::STATUS_PURCHASED]);
        $result = $this->service->retryCreation($shipment, $this->owner);

        $this->assertNotNull($result->tracking_number);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-004: Normalized Error Model (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_logged_on_creation_failure(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andThrow(new \Exception('DHL server error', 500));

        try {
            $this->service->createAtCarrier($shipment, $this->owner);
        } catch (BusinessException $e) {}

        $errors = CarrierError::where('shipment_id', $shipment->id)->get();
        $this->assertCount(1, $errors);
        $this->assertEquals(CarrierError::OP_CREATE_SHIPMENT, $errors->first()->operation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_maps_dhl_500_to_internal_code(): void
    {
        $error = CarrierError::fromDhlResponse(
            CarrierError::OP_CREATE_SHIPMENT,
            500,
            ['message' => 'Internal Server Error'],
            'test-correlation',
        );

        $this->assertEquals(CarrierError::ERR_CARRIER_INTERNAL, $error->internal_code);
        $this->assertTrue($error->is_retriable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_maps_dhl_401_to_auth_failed(): void
    {
        $error = CarrierError::fromDhlResponse(
            CarrierError::OP_CREATE_SHIPMENT,
            401,
            ['message' => 'Unauthorized'],
            'test-correlation',
        );

        $this->assertEquals(CarrierError::ERR_AUTH_FAILED, $error->internal_code);
        $this->assertFalse($error->is_retriable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_maps_dhl_429_to_rate_limited(): void
    {
        $error = CarrierError::fromDhlResponse(
            CarrierError::OP_FETCH_RATES,
            429,
            ['message' => 'Rate limit exceeded'],
            'test-correlation',
        );

        $this->assertEquals(CarrierError::ERR_RATE_LIMITED, $error->internal_code);
        $this->assertTrue($error->is_retriable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_retry_backoff_calculation(): void
    {
        $error = CarrierError::factory()->create([
            'is_retriable'  => true,
            'retry_attempt' => 0,
            'max_retries'   => 3,
        ]);

        $nextRetry = $error->calculateNextRetry();
        $this->assertNotNull($nextRetry);

        // Attempt 2 should be later
        $error->update(['retry_attempt' => 1]);
        $nextRetry2 = $error->calculateNextRetry();
        $this->assertTrue($nextRetry2 > $nextRetry);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_marked_resolved(): void
    {
        $error = CarrierError::factory()->create(['was_resolved' => false]);

        $error->markResolved();

        $this->assertTrue($error->fresh()->was_resolved);
        $this->assertNotNull($error->fresh()->resolved_at);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-005: Re-fetch Label (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refetch_label_for_label_pending_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_READY_FOR_PICKUP,
        ]);

        $carrierShipment = CarrierShipment::factory()->labelPending()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->dhlApiMock->shouldReceive('fetchLabel')
            ->once()
            ->andReturn([
                'content' => base64_encode('new label PDF content'),
                'format'  => 'pdf',
                'url'     => null,
            ]);

        $document = $this->service->refetchLabel($shipment, $this->owner);

        $this->assertEquals('label', $document->type);
        $this->assertTrue($document->is_available);
        $this->assertEquals(CarrierShipment::STATUS_LABEL_READY, $carrierShipment->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refetch_label_with_different_format(): void
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
                'content' => base64_encode('^XA^FO50,50^FDHello^FS^XZ'),
                'format'  => 'zpl',
                'url'     => null,
            ]);

        $document = $this->service->refetchLabel($shipment, $this->owner, 'zpl');

        $this->assertEquals('zpl', $document->format);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refetch_label_fails_for_non_created_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Shipment::STATUS_DRAFT,
        ]);
        // No carrier shipment exists

        $this->expectException(BusinessException::class);
        $this->service->refetchLabel($shipment, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refetch_label_records_fetch_attempt(): void
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
                'content' => base64_encode('label content'),
                'format'  => 'pdf',
                'url'     => null,
            ]);

        $document = $this->service->refetchLabel($shipment, $this->owner);

        $this->assertNotNull($document->created_at);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-006: Cancel at Carrier (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_at_carrier_succeeds(): void
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
            ->andReturn(['cancellationId' => 'CXL-123', 'status' => 'cancelled']);

        $result = $this->service->cancelAtCarrier($shipment, $this->owner, 'Customer request');

        $this->assertEquals(CarrierShipment::STATUS_CANCELLED, $result->status);
        $this->assertEquals('Customer request', $result->cancellation_reason);
        $this->assertNotNull($result->cancelled_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_updates_shipment_status(): void
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
            ->andReturn(['cancellationId' => 'CXL-123']);

        $this->service->cancelAtCarrier($shipment, $this->owner);

        $this->assertEquals(Shipment::STATUS_CANCELLED, $shipment->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_fails_for_already_cancelled(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
        ]);

        CarrierShipment::factory()->cancelled()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->cancelAtCarrier($shipment, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_fails_after_deadline(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
        ]);

        CarrierShipment::factory()->create([
            'shipment_id'           => $shipment->id,
            'account_id'            => $this->account->id,
            'status'                => CarrierShipment::STATUS_LABEL_READY,
            'is_cancellable'        => true,
            'cancellation_deadline' => now()->subHour(), // Past deadline
        ]);

        $this->expectException(BusinessException::class);
        $this->service->cancelAtCarrier($shipment, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_fails_when_carrier_rejects(): void
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
            ->andThrow(new \Exception('Cancellation rejected by carrier', 422));

        $this->expectException(BusinessException::class);
        $this->service->cancelAtCarrier($shipment, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-007: Multiple Label Formats (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_with_pdf_format(): void
    {
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $result = $this->service->createAtCarrier($shipment, $this->owner, 'pdf');

        $this->assertEquals('pdf', $result->label_format);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_with_zpl_format(): void
    {
        $shipment = $this->createReadyShipment();

        $response = $this->fakeDhlCreateResponse();
        $response['documents'][0]['format'] = 'zpl';

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($response);

        $result = $this->service->createAtCarrier($shipment, $this->owner, 'zpl');

        $this->assertEquals('zpl', $result->label_format);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_uses_account_default_format(): void
    {
        $this->account->update(['settings' => ['label_format' => 'zpl', 'label_size' => '4x8']]);
        $shipment = $this->createReadyShipment();

        $this->dhlApiMock->shouldReceive('createShipment')
            ->once()
            ->andReturn($this->fakeDhlCreateResponse());

        $result = $this->service->createAtCarrier($shipment, $this->owner);

        $this->assertEquals('zpl', $result->label_format);
        $this->assertEquals('4x8', $result->label_size);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-CR-008: Secure Document Download (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_download_document_returns_content(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $carrierShipment = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $document = CarrierDocument::factory()->create([
            'carrier_shipment_id' => $carrierShipment->id,
            'shipment_id'         => $shipment->id,
        ]);

        $result = $this->service->getDocumentForDownload($document->id, $shipment, $this->owner);

        $this->assertNotNull($result['content']);
        $this->assertEquals('pdf', $result['format']);
        $this->assertArrayNotHasKey('net_rate', $result); // No financial data!
        $this->assertArrayNotHasKey('retail_rate', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_download_increments_counter(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $carrierShipment = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $document = CarrierDocument::factory()->create([
            'carrier_shipment_id' => $carrierShipment->id,
            'shipment_id'         => $shipment->id,
            'download_count'      => 0,
        ]);

        $this->service->getDocumentForDownload($document->id, $shipment, $this->owner);

        $this->assertEquals(1, $document->fresh()->download_count);
        $this->assertNotNull($document->fresh()->last_downloaded_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_download_unavailable_document_fails(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $carrierShipment = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $document = CarrierDocument::factory()->unavailable()->create([
            'carrier_shipment_id' => $carrierShipment->id,
            'shipment_id'         => $shipment->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->getDocumentForDownload($document->id, $shipment, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_documents_returns_metadata_only(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);
        $carrierShipment = CarrierShipment::factory()->create([
            'shipment_id' => $shipment->id,
            'account_id'  => $this->account->id,
        ]);

        CarrierDocument::factory()->count(3)->create([
            'carrier_shipment_id' => $carrierShipment->id,
            'shipment_id'         => $shipment->id,
        ]);

        $documents = $this->service->listDocuments($shipment);

        $this->assertCount(3, $documents);
        // Ensure no content in listing
        foreach ($documents as $doc) {
            $this->assertArrayNotHasKey('content_base64', $doc);
            $this->assertArrayNotHasKey('content', $doc);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_document_print_count_tracking(): void
    {
        $document = CarrierDocument::factory()->create([
            'print_count' => 0,
        ]);

        $document->recordPrint();
        $this->assertEquals(1, $document->fresh()->print_count);
        $this->assertNotNull($document->fresh()->last_printed_at);

        $document->recordPrint();
        $this->assertEquals(2, $document->fresh()->print_count);
    }

    // ═══════════════════════════════════════════════════════════
    // Model Tests (7 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_shipment_can_cancel_check(): void
    {
        $cs = CarrierShipment::factory()->labelReady()->create([
            'account_id'            => $this->account->id,
            'is_cancellable'        => true,
            'cancellation_deadline' => now()->addHours(24),
        ]);

        $this->assertTrue($cs->canCancel());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_shipment_cannot_cancel_when_failed(): void
    {
        $cs = CarrierShipment::factory()->failed()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($cs->canCancel());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_shipment_retry_eligibility(): void
    {
        $cs = CarrierShipment::factory()->failed()->create([
            'account_id'    => $this->account->id,
            'attempt_count' => 1,
        ]);

        $this->assertTrue($cs->canRetry(3));

        $cs->update(['attempt_count' => 3]);
        $this->assertFalse($cs->canRetry(3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_document_download_url_validity(): void
    {
        $doc = CarrierDocument::factory()->withDownloadUrl()->create();
        $this->assertTrue($doc->isDownloadUrlValid());

        $doc->update(['download_url_expires_at' => now()->subMinute()]);
        $this->assertFalse($doc->isDownloadUrlValid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_error_retriable_scope(): void
    {
        CarrierError::factory()->retriable()->create();
        CarrierError::factory()->nonRetriable()->create();
        CarrierError::factory()->resolved()->create();

        $retriable = CarrierError::retriable()->get();
        $this->assertCount(1, $retriable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_error_get_internal_message(): void
    {
        $msg = CarrierError::getInternalMessage(CarrierError::ERR_NETWORK_TIMEOUT);
        $this->assertStringContainsString('timed out', $msg);

        $msg = CarrierError::getInternalMessage(CarrierError::ERR_AUTH_FAILED);
        $this->assertStringContainsString('authentication', $msg);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_errors_list(): void
    {
        $shipment = Shipment::factory()->create(['account_id' => $this->account->id]);

        CarrierError::factory()->count(3)->create([
            'shipment_id' => $shipment->id,
        ]);

        $errors = $this->service->getErrors($shipment);
        $this->assertCount(3, $errors);
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
