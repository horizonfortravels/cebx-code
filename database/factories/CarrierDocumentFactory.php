<?php

namespace Database\Factories;

use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CarrierDocumentFactory extends Factory
{
    protected $model = CarrierDocument::class;

    public function configure(): static
    {
        return $this->afterMaking(function (CarrierDocument $document): void {
            $mode = (string) ($document->retrieval_mode ?? CarrierDocument::RETRIEVAL_INLINE);

            if ($mode === CarrierDocument::RETRIEVAL_STORED_OBJECT) {
                $document->content_base64 = null;
                $document->download_url = null;
                $document->storage_disk = $document->storage_disk ?: config('filesystems.default', 'local');
                $document->storage_path = $document->storage_path ?: 'carrier-documents/test/'.Str::uuid()->toString();

                return;
            }

            if ($mode === CarrierDocument::RETRIEVAL_URL) {
                $document->content_base64 = null;
                $document->storage_disk = null;
                $document->storage_path = null;
                $document->download_url = $document->download_url ?: 'https://example.test/carrier-documents/'.Str::uuid()->toString();
            }
        });
    }

    public function definition(): array
    {
        $content = 'JVBERi0xLjQKMSAwIG9iago8PC9UeXBlL0NhdGFsb2c+PgplbmRvYmoK'; // Fake PDF base64

        return [
            'carrier_shipment_id' => CarrierShipment::factory(),
            'shipment_id' => Shipment::factory(),
            'carrier_code' => 'fedex',
            'type' => CarrierDocument::TYPE_LABEL,
            'format' => 'pdf',
            'mime_type' => 'application/pdf',
            'source' => CarrierDocument::SOURCE_CARRIER,
            'retrieval_mode' => CarrierDocument::RETRIEVAL_INLINE,
            'original_filename' => 'label_test.pdf',
            'file_size' => strlen(base64_decode($content)),
            'checksum' => hash('sha256', base64_decode($content)),
            'content_base64' => $content,
            'is_available' => true,
        ];
    }

    public function label(): static
    {
        return $this->state(['type' => CarrierDocument::TYPE_LABEL]);
    }

    public function commercialInvoice(): static
    {
        return $this->state([
            'type' => CarrierDocument::TYPE_COMMERCIAL_INVOICE,
            'original_filename' => 'commercial_invoice.pdf',
        ]);
    }

    public function customsDeclaration(): static
    {
        return $this->state([
            'type' => CarrierDocument::TYPE_CUSTOMS_DECLARATION,
            'original_filename' => 'customs_declaration.pdf',
        ]);
    }

    public function zplFormat(): static
    {
        return $this->state([
            'format' => 'zpl',
            'mime_type' => 'application/x-zpl',
        ]);
    }

    public function unavailable(): static
    {
        return $this->state([
            'is_available' => false,
            'content_base64' => null,
        ]);
    }

    public function withDownloadUrl(): static
    {
        return $this->state([
            'content_base64' => null,
            'download_url' => 'https://express.api.dhl.com/mydhlapi/labels/download/abc123',
            'download_url_expires_at' => now()->addHour(),
            'retrieval_mode' => CarrierDocument::RETRIEVAL_URL,
        ]);
    }
}
