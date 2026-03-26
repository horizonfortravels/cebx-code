<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\CarrierService;
use App\Support\PortalShipmentLabeler;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ShipmentDocumentWebController extends Controller
{
    public function __construct(private CarrierService $carrierService) {}

    public function b2cIndex(Request $request, string $id): View
    {
        return $this->documentsPage($request, $id, 'b2c');
    }

    public function b2cDownload(Request $request, string $id, string $documentId, ?string $downloadName = null): Response|RedirectResponse
    {
        return $this->downloadDocument($request, $id, $documentId, $downloadName);
    }

    public function b2cPreview(Request $request, string $id, string $documentId, ?string $previewName = null): Response|RedirectResponse
    {
        return $this->previewDocument($request, $id, $documentId, 'b2c', $previewName);
    }

    public function b2bIndex(Request $request, string $id): View
    {
        return $this->documentsPage($request, $id, 'b2b');
    }

    public function b2bDownload(Request $request, string $id, string $documentId, ?string $downloadName = null): Response|RedirectResponse
    {
        return $this->downloadDocument($request, $id, $documentId, $downloadName);
    }

    public function b2bPreview(Request $request, string $id, string $documentId, ?string $previewName = null): Response|RedirectResponse
    {
        return $this->previewDocument($request, $id, $documentId, 'b2b', $previewName);
    }

    private function documentsPage(Request $request, string $shipmentId, string $portal): View
    {
        $user = $request->user();
        $shipment = Shipment::query()
            ->where('id', $shipmentId)
            ->where('account_id', (string) $user->account_id)
            ->with(['carrierShipment', 'selectedRateOption'])
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $documents = collect($this->carrierService->listDocuments($shipment))
            ->map(function (array $document) use ($portal, $shipment): array {
                return $this->decorateDocumentRoutes($document, $portal, $shipment);
            })
            ->all();

        return view('pages.portal.shipments.documents', [
            'shipment' => $shipment,
            'documents' => $documents,
            'portalConfig' => $this->portalConfig($portal),
        ]);
    }

    private function downloadDocument(Request $request, string $shipmentId, string $documentId, ?string $downloadName = null): Response|RedirectResponse
    {
        $user = $request->user();
        $shipment = Shipment::query()
            ->where('id', $shipmentId)
            ->where('account_id', (string) $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $docData = $this->carrierService->getDocumentForDownload($documentId, $shipment, $user);

        if (! empty($docData['download_url']) && empty($docData['content'])) {
            return redirect()->away((string) $docData['download_url']);
        }

        $content = (string) ($docData['content'] ?? '');
        $headers = array_filter([
            'Content-Type' => (string) ($docData['mime_type'] ?? 'application/octet-stream'),
            'Content-Length' => (string) ($docData['file_size'] ?? strlen($content)),
            'X-Checksum-SHA256' => (string) ($docData['checksum'] ?? ''),
            'X-Content-Type-Options' => 'nosniff',
        ], static fn ($value) => $value !== '');

        if (! empty($docData['storage_path']) && ! empty($docData['storage_disk'])) {
            return Storage::disk((string) $docData['storage_disk'])->download(
                (string) $docData['storage_path'],
                (string) ($docData['filename'] ?? $downloadName ?? 'document.bin'),
                $headers
            );
        }

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            (string) ($docData['filename'] ?? $downloadName ?? 'document.bin'),
            $headers
        );
    }

    private function previewDocument(
        Request $request,
        string $shipmentId,
        string $documentId,
        string $portal,
        ?string $previewName = null
    ): Response|RedirectResponse {
        $user = $request->user();
        $shipment = Shipment::query()
            ->where('id', $shipmentId)
            ->where('account_id', (string) $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $docData = $this->carrierService->getDocumentForDownload($documentId, $shipment, $user);

        if (! $this->isPdfPreviewable($docData)) {
            return redirect()->route($portal . '.shipments.documents.download', [
                'id' => (string) $shipment->id,
                'documentId' => $documentId,
                'downloadName' => (string) ($docData['filename'] ?? $previewName ?? 'document.bin'),
            ]);
        }

        if (! empty($docData['download_url']) && empty($docData['content'])) {
            return redirect()->away((string) $docData['download_url']);
        }

        $filename = (string) ($docData['filename'] ?? $previewName ?? 'document.pdf');
        $content = (string) ($docData['content'] ?? '');
        $headers = array_filter([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($filename)),
            'Content-Length' => (string) ($docData['file_size'] ?? strlen($content)),
            'X-Checksum-SHA256' => (string) ($docData['checksum'] ?? ''),
            'X-Content-Type-Options' => 'nosniff',
        ], static fn ($value) => $value !== '');

        if (! empty($docData['storage_path']) && ! empty($docData['storage_disk'])) {
            $stream = Storage::disk((string) $docData['storage_disk'])->readStream((string) $docData['storage_path']);

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

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function decorateDocumentRoutes(array $document, string $portal, Shipment $shipment): array
    {
        $filename = (string) ($document['filename'] ?? 'document.bin');
        $downloadRoute = route($portal . '.shipments.documents.download', [
            'id' => (string) $shipment->id,
            'documentId' => (string) $document['id'],
            'downloadName' => $filename,
        ]);
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
            'download_route' => $downloadRoute,
            'previewable' => $previewable,
            'preview_route' => $previewable ? route($portal . '.shipments.documents.preview', [
                'id' => (string) $shipment->id,
                'documentId' => (string) $document['id'],
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

    /**
     * @return array<string, string>
     */
    private function portalConfig(string $portal): array
    {
        if ($portal === 'b2b') {
            return [
                'label' => __('portal_shipments.common.portal_b2b'),
                'dashboard_route' => 'b2b.dashboard',
                'shipments_index_route' => 'b2b.shipments.index',
                'offers_route' => 'b2b.shipments.offers',
                'declaration_route' => 'b2b.shipments.declaration',
            ];
        }

        return [
            'label' => __('portal_shipments.common.portal_b2c'),
            'dashboard_route' => 'b2c.dashboard',
            'shipments_index_route' => 'b2c.shipments.index',
            'offers_route' => 'b2c.shipments.offers',
            'declaration_route' => 'b2c.shipments.declaration',
        ];
    }
}
