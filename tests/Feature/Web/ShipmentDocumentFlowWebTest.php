<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentDocumentFlowWebTest extends TestCase
{
    public function test_b2c_individual_user_can_view_shipment_documents(): void
    {
        $user = $this->createPortalDocumentUser('individual', 'individual');
        $shipment = $this->createIssuedShipmentWithDocument($user);

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments/' . $shipment->id . '/documents')
            ->assertOk()
            ->assertSee('وثائق الشحنة وملفات الناقل')
            ->assertSee('تنزيل المستند')
            ->assertSee('label_test.pdf');
    }

    #[DataProvider('organizationPersonaProvider')]
    public function test_b2b_personas_can_view_shipment_documents(string $persona): void
    {
        $user = $this->createPortalDocumentUser('organization', $persona);
        $shipment = $this->createIssuedShipmentWithDocument($user);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents')
            ->assertOk()
            ->assertSee('وثائق الشحنة وملفات الناقل')
            ->assertSee('FedEx')
            ->assertSee('تنزيل المستند');
    }

    public function test_cross_tenant_document_page_access_is_denied(): void
    {
        $userA = $this->createPortalDocumentUser('organization', 'organization_owner');
        $userB = $this->createPortalDocumentUser('organization', 'organization_owner');
        $shipmentB = $this->createIssuedShipmentWithDocument($userB);

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments/' . $shipmentB->id . '/documents')
            ->assertNotFound();
    }

    public function test_label_download_returns_valid_pdf_with_normalized_filename(): void
    {
        Storage::fake('local');

        $user = $this->createPortalDocumentUser('organization', 'organization_owner');
        $shipment = Shipment::factory()->create([
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'reference_number' => 'DOC-PDF-01',
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
        ]);

        $carrierShipment = CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'tracking_number' => '794699991111',
        ]);

        $pdfBinary = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
        $storagePath = 'carrier-documents/' . $shipment->id . '/' . $carrierShipment->id . '/opaque-label';
        Storage::disk('local')->put($storagePath, $pdfBinary);

        $document = CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
            'format' => 'pdf',
            'mime_type' => 'application/octet-stream',
            'retrieval_mode' => CarrierDocument::RETRIEVAL_STORED_OBJECT,
            'storage_disk' => 'local',
            'storage_path' => $storagePath,
            'original_filename' => 'a1629e68-7142-4dd0-88c2-508b93fbee14',
            'content_base64' => null,
            'file_size' => strlen($pdfBinary),
            'checksum' => hash('sha256', $pdfBinary),
        ]);

        $response = $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id . '/documents/' . $document->id);

        $content = $response->streamedContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('label_794699991111.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertNotSame('', $content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function organizationPersonaProvider(): array
    {
        return [
            'organization_owner' => ['organization_owner'],
            'organization_admin' => ['organization_admin'],
            'staff' => ['staff'],
        ];
    }

    private function createPortalDocumentUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Docs ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Docs ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['shipments.read'], 'shipment_document_web_' . $persona);

        return $user;
    }

    private function createIssuedShipmentWithDocument(User $user): Shipment
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
            'tracking_number' => '794699999999',
        ]);

        CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
        ]);

        return $shipment;
    }
}
