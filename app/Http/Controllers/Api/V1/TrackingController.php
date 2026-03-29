<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\PublicTrackingService;
use App\Services\TrackingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TrackingController — FR-TR-001→007
 *
 * POST   /webhooks/dhl/tracking           — FR-TR-001/002: Receive DHL webhook
 * GET    /shipments/{id}/tracking/timeline — FR-TR-005: Tracking timeline
 * GET    /shipments/{id}/tracking/events   — FR-TR-005: Raw events list
 * GET    /tracking/search                  — FR-TR-005: Search by status/tracking
 * GET    /tracking/dashboard               — FR-TR-006: Status dashboard
 * POST   /shipments/{id}/tracking/subscribe — FR-TR-004: Subscribe to updates
 * DELETE /tracking/subscriptions/{id}       — FR-TR-004: Unsubscribe
 * GET    /shipments/{id}/exceptions         — FR-TR-007: View exceptions
 * POST   /exceptions/{id}/acknowledge       — FR-TR-007: Acknowledge
 * POST   /exceptions/{id}/resolve           — FR-TR-007: Resolve
 * POST   /exceptions/{id}/escalate          — FR-TR-007: Escalate
 * GET    /tracking/status-mappings          — FR-TR-004: List status mappings
 * POST   /tracking/poll/{trackingNumber}    — FR-TR-001: Manual poll
 */
class TrackingController extends Controller
{
    public function __construct(
        private TrackingService $trackingService,
        private PublicTrackingService $publicTracking,
    ) {}

    /**
     * FR-TR-001/002: Receive DHL tracking webhook (public endpoint).
     */
    public function handleDhlWebhook(Request $request): JsonResponse
    {
        $result = $this->trackingService->processWebhook(
            $request->all(),
            $request->headers->all(),
            $request->ip()
        );

        $httpStatus = $result['status'] === 'rejected' ? 403 : 200;

        return response()->json($result, $httpStatus);
    }

    /**
     * FR-TR-005: Get tracking timeline for a shipment.
     */
    public function timeline(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data'   => $this->trackingService->getTimeline($shipment),
        ]);
    }

    public function events(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data' => $this->trackingService->getEvents($shipment),
        ]);
    }

    public function status(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data' => $this->trackingService->getCurrentStatus($shipment),
        ]);
    }

    /**
     * FR-TR-005: Search/filter shipments by tracking status.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'status'          => 'nullable|string',
            'tracking_number' => 'nullable|string',
            'date_from'       => 'nullable|date',
            'date_to'         => 'nullable|date',
            'per_page'        => 'nullable|integer|min:1|max:100',
        ]);

        $results = $this->trackingService->searchByStatus(
            $user->account,
            $request->input('status'),
            $request->input('tracking_number'),
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('per_page', 20)
        );

        return response()->json([
            'status' => 'success',
            'data'   => $results,
        ]);
    }

    /**
     * FR-TR-006: Tracking status dashboard.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->trackingService->getStatusDashboard(
                $user->account,
                $request->input('date_from'),
                $request->input('date_to')
            ),
        ]);
    }

    /**
     * FR-TR-004: Subscribe to shipment tracking updates.
     */
    public function subscribe(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        $data = $request->validate([
            'channel'         => 'required|in:email,sms,webhook,in_app',
            'destination'     => 'required|string|max:500',
            'subscriber_name' => 'nullable|string|max:200',
            'event_types'     => 'nullable|array',
            'event_types.*'   => 'string',
            'language'        => 'nullable|string|max:5',
        ]);

        $sub = $this->trackingService->subscribe($data, $shipment, $user);

        return response()->json([
            'status' => 'success',
            'data'   => $sub,
        ], 201);
    }

    /**
     * FR-TR-004: Unsubscribe from tracking updates.
     */
    public function unsubscribe(Request $request, string $subscriptionId): JsonResponse
    {
        $this->trackingService->unsubscribe($subscriptionId);

        return response()->json(['status' => 'success', 'message' => 'Unsubscribed']);
    }

    /**
     * FR-TR-007: Get exceptions for a shipment.
     */
    public function exceptions(Request $request, string $shipmentId): JsonResponse
    {
        $user = $request->user();
        $shipment = Shipment::where('id', $shipmentId)
            ->where('account_id', $user->account_id)
            ->firstOrFail();

        $this->authorize('view', $shipment);

        return response()->json([
            'status' => 'success',
            'data'   => $this->trackingService->getExceptions($shipment),
        ]);
    }

    /**
     * FR-TR-007: Acknowledge an exception.
     */
    public function acknowledgeException(Request $request, string $exceptionId): JsonResponse
    {
        $result = $this->trackingService->acknowledgeException($exceptionId, $request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * FR-TR-007: Resolve an exception.
     */
    public function resolveException(Request $request, string $exceptionId): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $result = $this->trackingService->resolveException($exceptionId, $data['notes'], $request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * FR-TR-007: Escalate an exception.
     */
    public function escalateException(Request $request, string $exceptionId): JsonResponse
    {
        $exception = \App\Models\ShipmentException::findOrFail($exceptionId);
        $exception->escalate();

        return response()->json([
            'status' => 'success',
            'data'   => $exception,
        ]);
    }

    /**
     * FR-TR-004: Get status mappings.
     */
    public function statusMappings(Request $request): JsonResponse
    {
        $carrier = $request->input('carrier', 'dhl');
        $mappings = \App\Models\StatusMapping::forCarrier($carrier)->get();

        return response()->json([
            'status' => 'success',
            'data'   => $mappings,
        ]);
    }

    /**
     * FR-TR-001: Manual poll for a specific tracking number.
     */
    public function manualPoll(Request $request, string $trackingNumber): JsonResponse
    {
        $result = $this->trackingService->pollTrackingUpdates([$trackingNumber]);

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * FR-TR-007: External API for tracking query.
     */
    public function apiTrack(Request $request, string $token): JsonResponse
    {
        try {
            $payload = $this->publicTracking->apiPayload($token);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tracking not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $payload,
        ]);
    }
}
