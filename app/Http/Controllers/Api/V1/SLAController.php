<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\SLAEngineService;
use Illuminate\Http\JsonResponse;

class SLAController extends Controller
{
    public function __construct(protected SLAEngineService $sla) {}

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        return response()->json(['data' => $this->sla->dashboard($this->currentAccountId())]);
    }

    public function check(string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('view', [self::class, $shipment]);

        return response()->json(['data' => $this->sla->checkSLA($shipment)]);
    }

    public function scanBreaches(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $activeStatuses = [
            'booked',
            'picked_up',
            'at_origin_hub',
            'export_clearance',
            'in_transit',
            'at_destination_hub',
            'import_clearance',
            'out_for_delivery',
        ];

        $shipments = Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->whereIn('status', $activeStatuses)
            ->get();

        $breaches = [];
        foreach ($shipments as $shipment) {
            $sla = $this->sla->checkSLA($shipment);
            if (($sla['phase_sla']['breached'] ?? false) || ($sla['total_sla']['breached'] ?? false)) {
                $breaches[] = $sla;
            }
        }

        return response()->json(['data' => [
            'total_checked' => $shipments->count(),
            'breaches_found' => count($breaches),
            'breaches' => $breaches,
            'scanned_at' => now()->toIso8601String(),
        ]]);
    }

    public function atRisk(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $shipments = Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->get();

        $atRisk = [];
        foreach ($shipments as $shipment) {
            $sla = $this->sla->checkSLA($shipment);
            if (($sla['phase_sla']['percentage'] ?? 0) >= 75 || ($sla['total_sla']['percentage'] ?? 0) >= 75) {
                $atRisk[] = [
                    'shipment' => [
                        'id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'status' => $shipment->status,
                        'receiver_name' => $shipment->receiver_name,
                    ],
                    'sla' => $sla,
                ];
            }
        }

        usort(
            $atRisk,
            static fn (array $left, array $right): int => ($right['sla']['phase_sla']['percentage'] ?? 0)
                <=> ($left['sla']['phase_sla']['percentage'] ?? 0)
        );

        return response()->json(['data' => [
            'at_risk' => $atRisk,
            'count' => count($atRisk),
        ]]);
    }

    private function findShipmentForCurrentAccount(string $id): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
