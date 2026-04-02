<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CarrierService;
use App\Support\Internal\InternalControlPlane;
use App\Support\PortalShipmentLabeler;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InternalShipmentDocumentController extends Controller
{
    public function __construct(private readonly CarrierService $carrierService) {}

    public function index(Request $request, string $shipment, InternalControlPlane $controlPlane): View
    {
        $shipmentModel = $this->shipmentQuery()->findOrFail($shipment);
        $documents = collect($this->carrierService->listDocuments($shipmentModel))
            ->map(fn (array $document): array => $this->decorateDocumentRoutes($document, $shipmentModel))
            ->values();

        return view('pages.admin.shipments-documents', [
            'shipment' => $shipmentModel,
            'documents' => $documents,
            'documentHeadline' => $documents->isEmpty()
                ? 'No shipment documents are currently available through the internal carrier-artifact seam.'
                : number_format($documents->count()) . ' document artifact(s) are currently available.',
            'shipmentSummary' => [
                'reference' => (string) ($shipmentModel->reference_number ?: $shipmentModel->id),
                'carrier_label' => PortalShipmentLabeler::carrier(
                    (string) ($shipmentModel->carrier_code ?: $shipmentModel->carrierShipment?->carrier_code ?: ''),
                    (string) ($shipmentModel->carrier_name ?: $shipmentModel->carrierShipment?->carrier_name ?: '')
                ),
                'tracking_number' => (string) (
                    $shipmentModel->tracking_number
                    ?: $shipmentModel->carrier_tracking_number
                    ?: $shipmentModel->carrierShipment?->tracking_number
                    ?: 'Not available'
                ),
                'awb_number' => (string) ($shipmentModel->carrierShipment?->awb_number ?: 'Not available'),
            ],
            'canViewShipmentDetail' => $this->canViewShipmentDetail($request->user(), $controlPlane),
        ]);
    }

    public function download(Request $request, string $shipment, string $document, ?string $downloadName = null): Response|RedirectResponse
    {
        $shipmentModel = $this->shipmentQuery()->findOrFail($shipment);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $documentData = $this->carrierService->getDocumentForDownload($document, $shipmentModel, $user);

        if (! empty($documentData['download_url']) && empty($documentData['content'])) {
            return redirect()->away((string) $documentData['download_url']);
        }

        $content = (string) ($documentData['content'] ?? '');
        $headers = array_filter([
            'Content-Type' => (string) ($documentData['mime_type'] ?? 'application/octet-stream'),
            'Content-Length' => (string) ($documentData['file_size'] ?? strlen($content)),
            'X-Checksum-SHA256' => (string) ($documentData['checksum'] ?? ''),
            'X-Content-Type-Options' => 'nosniff',
        ], static fn ($value) => $value !== '');

        if (! empty($documentData['storage_path']) && ! empty($documentData['storage_disk'])) {
            return Storage::disk((string) $documentData['storage_disk'])->download(
                (string) $documentData['storage_path'],
                (string) ($documentData['filename'] ?? $downloadName ?? 'document.bin'),
                $headers
            );
        }

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            (string) ($documentData['filename'] ?? $downloadName ?? 'document.bin'),
            $headers
        );
    }

    public function preview(Request $request, string $shipment, string $document, ?string $previewName = null): Response|RedirectResponse
    {
        $shipmentModel = $this->shipmentQuery()->findOrFail($shipment);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $documentData = $this->carrierService->getDocumentForDownload($document, $shipmentModel, $user);

        if (! $this->isPdfPreviewable($documentData)) {
            return redirect()->route('internal.shipments.documents.download', [
                'shipment' => (string) $shipmentModel->id,
                'document' => $document,
                'downloadName' => (string) ($documentData['filename'] ?? $previewName ?? 'document.bin'),
            ]);
        }

        if (! empty($documentData['download_url']) && empty($documentData['content'])) {
            return redirect()->away((string) $documentData['download_url']);
        }

        $filename = (string) ($documentData['filename'] ?? $previewName ?? 'document.pdf');
        $content = (string) ($documentData['content'] ?? '');
        $headers = array_filter([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($filename)),
            'Content-Length' => (string) ($documentData['file_size'] ?? strlen($content)),
            'X-Checksum-SHA256' => (string) ($documentData['checksum'] ?? ''),
            'X-Content-Type-Options' => 'nosniff',
        ], static fn ($value) => $value !== '');

        if (! empty($documentData['storage_path']) && ! empty($documentData['storage_disk'])) {
            $stream = Storage::disk((string) $documentData['storage_disk'])->readStream((string) $documentData['storage_path']);

            return response()->stream(
                static function () use ($stream): void {
                    if (is_resource($stream)) {
                        fpassthru($stream);
                        fclose($stream);
                    }
                },
                200,
                $headers
            );
        }

        return response()->stream(
            static function () use ($content): void {
                echo $content;
            },
            200,
            $headers
        );
    }

    private function shipmentQuery()
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->with(['carrierShipment']);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function decorateDocumentRoutes(array $document, Shipment $shipment): array
    {
        $filename = (string) ($document['filename'] ?? 'document.bin');
        $previewable = $this->isPdfPreviewable($document);

        return array_merge($document, [
            'document_type_label' => PortalShipmentLabeler::documentType(
                (string) ($document['document_type'] ?? $document['type'] ?? ''),
                (string) ($document['document_type'] ?? $document['type'] ?? '')
            ),
            'carrier_label' => PortalShipmentLabeler::carrier(
                (string) ($document['carrier_code'] ?? ''),
                (string) ($document['carrier_name'] ?? '')
            ),
            'format_label' => PortalShipmentLabeler::documentFormat(
                (string) ($document['file_format'] ?? $document['format'] ?? ''),
                strtoupper((string) ($document['file_format'] ?? $document['format'] ?? ''))
            ),
            'retrieval_mode_label' => PortalShipmentLabeler::retrievalMode(
                (string) ($document['retrieval_mode'] ?? ''),
                (string) ($document['retrieval_mode'] ?? '')
            ),
            'created_at_display' => $this->displayDateTime($document['created_at'] ?? null),
            'size_label' => $this->humanFileSize($document['size'] ?? null),
            'availability_label' => ! empty($document['available']) ? 'Available' : 'Unavailable',
            'download_route' => route('internal.shipments.documents.download', [
                'shipment' => (string) $shipment->id,
                'document' => (string) $document['id'],
                'downloadName' => $filename,
            ]),
            'previewable' => $previewable,
            'preview_route' => $previewable ? route('internal.shipments.documents.preview', [
                'shipment' => (string) $shipment->id,
                'document' => (string) $document['id'],
                'previewName' => $filename,
            ]) : null,
        ]);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function isPdfPreviewable(array $document): bool
    {
        $format = strtolower(trim((string) ($document['format'] ?? $document['file_format'] ?? '')));
        $mimeType = strtolower(trim((string) ($document['mime_type'] ?? '')));
        $filename = strtolower(trim((string) ($document['filename'] ?? '')));

        return $format === 'pdf'
            || $mimeType === 'application/pdf'
            || str_ends_with($filename, '.pdf');
    }

    private function canViewShipmentDetail(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('shipments.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DETAIL);
    }

    private function displayDateTime(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function humanFileSize(mixed $value): string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return 'Unknown';
        }

        $size = (float) $value;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
    }
}
