<?php

namespace Tests\Feature\Web;

use App\Models\CarrierDocument;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalShipmentDocumentWebTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;
    private CarrierDocument $labelDocument;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shipment = Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', 'SHP-I5A-D-001')
            ->firstOrFail();

        $this->labelDocument = CarrierDocument::query()
            ->where('shipment_id', (string) $this->shipment->id)
            ->where('original_filename', 'i5a-d-label.pdf')
            ->firstOrFail();
    }

    #[Test]
    public function internal_roles_with_document_access_can_open_the_internal_document_workspace(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.shipments.documents.index', $this->shipment))
                ->assertOk()
                ->assertSee('data-testid="internal-shipment-documents-workspace"', false)
                ->assertSee('data-testid="internal-shipment-document-row"', false)
                ->assertSee('data-testid="internal-shipment-document-preview-link"', false)
                ->assertSee('data-testid="internal-shipment-document-download-link"', false)
                ->assertSeeText('i5a-d-label.pdf')
                ->assertSeeText('Available')
                ->assertDontSeeText('content_base64')
                ->assertDontSeeText('storage_path');

            if ($email === 'e2e.internal.carrier_manager@example.test') {
                $response->assertDontSee('href="' . route('internal.shipments.show', $this->shipment) . '"', false);
            } else {
                $response->assertSee('href="' . route('internal.shipments.show', $this->shipment) . '"', false);
            }
        }
    }

    #[Test]
    public function allowed_internal_roles_can_preview_pdf_labels_inline(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.shipments.documents.preview', [
                    'shipment' => $this->shipment,
                    'document' => $this->labelDocument,
                    'previewName' => 'i5a-d-label.pdf',
                ]));

            $content = $response->streamedContent();

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString('i5a-d-label.pdf', (string) $response->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF', $content);
        }
    }

    #[Test]
    public function allowed_internal_roles_can_download_pdf_labels(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.shipments.documents.download', [
                    'shipment' => $this->shipment,
                    'document' => $this->labelDocument,
                    'downloadName' => 'i5a-d-label.pdf',
                ]));

            $content = $response->streamedContent();

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringContainsString('attachment;', strtolower((string) $response->headers->get('content-disposition')));
            $this->assertStringContainsString('i5a-d-label.pdf', (string) $response->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF', $content);
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_shipment_document_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.shipments.documents.index', $this->shipment))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.shipments.documents.preview', [
                'shipment' => $this->shipment,
                'document' => $this->labelDocument,
                'previewName' => 'i5a-d-label.pdf',
            ]))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.shipments.documents.download', [
                'shipment' => $this->shipment,
                'document' => $this->labelDocument,
                'downloadName' => 'i5a-d-label.pdf',
            ]))
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertDontSee('data-testid="internal-shipment-documents-workspace"', false);
    }
}
