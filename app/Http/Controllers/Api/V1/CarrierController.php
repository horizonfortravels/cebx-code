<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CarrierService;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CarrierController — FR-CR-001→008
 *
 * POST   /shipments/{id}/carrier/create    — FR-CR-001: Create at carrier
 * POST   /shipments/{id}/carrier/refetch   — FR-CR-005: Re-fetch label
 * POST   /shipments/{id}/carrier/cancel    — FR-CR-006: Cancel at carrier
 * GET    /shipments/{id}/carrier/status     — Carrier shipment status
 * GET    /shipments/{id}/documents          — FR-CR-008: List documents
 * GET    /shipments/{id}/documents/{docId}  — FR-CR-008: Download document
 * POST   /shipments/{id}/carrier/retry      — FR-CR-003: Retry failed
 * GET    /shipments/{id}/carrier/errors      — FR-CR-004: View errors
 */
class CarrierController extends Controller
{
    public function __construct(private CarrierService $carrierService) {}

    /**
     * FR-CR-001: Create shipment at carrier.
     */
    public function createAtCarrier(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('update', $shipment);

        $request->validate([
            'label_format' => 'nullable|in:pdf,zpl,png,epl',
            'label_size'   => 'nullable|in:4x6,4x8,A4,A5',
            'idempotency_key' => 'nullable|string|max:200',
            'correlation_id' => 'nullable|string|max:200',
        ]);

        $carrierShipment = $this->carrierService->createAtCarrier(
            $shipment,
            $user,
            $request->input('label_format'),
            $request->input('label_size'),
            $request->input('idempotency_key'),
            $request->input('correlation_id')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Shipment created at carrier successfully',
            'data'    => [
                'id'                  => $carrierShipment->id,
                'carrier_shipment_id' => $carrierShipment->carrier_shipment_id,
                'tracking_number'     => $carrierShipment->tracking_number,
                'awb_number'          => $carrierShipment->awb_number,
                'status'              => $carrierShipment->status,
                'carrier'             => $carrierShipment->carrier_code,
                'service_code'        => $carrierShipment->service_code,
                'correlation_id'      => $carrierShipment->correlation_id,
                'idempotency_key'     => $carrierShipment->idempotency_key,
                'label_format'        => $carrierShipment->label_format,
                'documents'           => $this->carrierService->listDocuments($shipment),
            ],
        ], 201);
    }

    /**
     * FR-CR-005: Re-fetch label for shipment.
     */
    public function refetchLabel(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('update', $shipment);

        $request->validate([
            'format' => 'nullable|in:pdf,zpl,png,epl',
        ]);

        $document = $this->carrierService->refetchLabel(
            $shipment,
            $user,
            $request->input('format')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Label re-fetched successfully',
            'data'    => [
                'document_id' => $document->id,
                'type'        => $document->type,
                'format'      => $document->format,
                'filename'    => $document->original_filename,
                'is_available' => $document->is_available,
            ],
        ]);
    }

    /**
     * FR-CR-006: Cancel shipment at carrier.
     */
    public function cancelAtCarrier(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('update', $shipment);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $carrierShipment = $this->carrierService->cancelAtCarrier(
            $shipment,
            $user,
            $request->input('reason', '')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Shipment cancelled at carrier',
            'data'    => [
                'carrier_status'    => $carrierShipment->status,
                'cancellation_id'   => $carrierShipment->cancellation_id,
                'cancelled_at'      => $carrierShipment->cancelled_at,
            ],
        ]);
    }

    /**
     * Get carrier shipment status.
     */
    public function carrierStatus(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->with('carrierShipment')
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $cs = $shipment->carrierShipment;

        return response()->json([
            'status' => 'success',
            'data'   => $cs ? [
                'carrier_shipment_id' => $cs->id,
                'carrier_code'        => $cs->carrier_code,
                'tracking_number'     => $cs->tracking_number,
                'awb_number'          => $cs->awb_number,
                'status'              => $cs->status,
                'is_cancellable'      => $cs->canCancel(),
                'cancellation_deadline' => $cs->cancellation_deadline,
                'label_format'        => $cs->label_format,
                'attempt_count'       => $cs->attempt_count,
                'created_at'          => $cs->created_at,
            ] : null,
        ]);
    }

    /**
     * FR-CR-008: List documents for shipment.
     */
    public function listDocuments(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data'   => $this->carrierService->listDocuments($shipment),
        ]);
    }

    /**
     * FR-CR-008: Download a specific document (secure, no financial data).
     */
    public function downloadDocument(Request $request, string $shipmentId, string $documentId): Response
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $docData = $this->carrierService->getDocumentForDownload($documentId, $shipment, $user);

        return response($docData['content'])
            ->header('Content-Type', $docData['mime_type'])
            ->header('Content-Disposition', "attachment; filename=\"{$docData['filename']}\"")
            ->header('Content-Length', $docData['file_size'])
            ->header('X-Checksum-SHA256', $docData['checksum'] ?? '');
    }

    /**
     * FR-CR-003: Retry failed carrier creation.
     */
    public function retryCreation(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('update', $shipment);

        $carrierShipment = $this->carrierService->retryCreation($shipment, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Carrier creation retried successfully',
            'data'    => [
                'carrier_shipment_id' => $carrierShipment->id,
                'tracking_number'     => $carrierShipment->tracking_number,
                'status'              => $carrierShipment->status,
                'attempt_count'       => $carrierShipment->attempt_count,
            ],
        ]);
    }

    /**
     * FR-CR-004: View carrier errors for shipment.
     */
    public function carrierErrors(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data'   => $this->carrierService->getErrors($shipment),
        ]);
    }
}
