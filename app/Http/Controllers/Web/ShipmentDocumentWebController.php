<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\CarrierService;
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

    public function b2bIndex(Request $request, string $id): View
    {
        return $this->documentsPage($request, $id, 'b2b');
    }

    public function b2bDownload(Request $request, string $id, string $documentId, ?string $downloadName = null): Response|RedirectResponse
    {
        return $this->downloadDocument($request, $id, $documentId, $downloadName);
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
                return array_merge($document, [
                    'download_route' => route($portal . '.shipments.documents.download', [
                        'id' => (string) $shipment->id,
                        'documentId' => (string) $document['id'],
                        'downloadName' => (string) $document['filename'],
                    ]),
                ]);
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

    /**
     * @return array<string, string>
     */
    private function portalConfig(string $portal): array
    {
        if ($portal === 'b2b') {
            return [
                'label' => 'بوابة الأعمال',
                'dashboard_route' => 'b2b.dashboard',
                'shipments_index_route' => 'b2b.shipments.index',
                'offers_route' => 'b2b.shipments.offers',
                'declaration_route' => 'b2b.shipments.declaration',
            ];
        }

        return [
            'label' => 'بوابة الأفراد',
            'dashboard_route' => 'b2c.dashboard',
            'shipments_index_route' => 'b2c.shipments.index',
            'offers_route' => 'b2c.shipments.offers',
            'declaration_route' => 'b2c.shipments.declaration',
        ];
    }
}
