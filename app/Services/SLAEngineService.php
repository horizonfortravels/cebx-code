<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * CBEX GROUP — SLA Engine Service
 *
 * Tracks SLA compliance for shipments, triggers escalations,
 * and manages delivery time windows.
 */
class SLAEngineService
{
    // ── SLA definitions by service level (hours) ─────────────
    protected array $slaTargets = [
        'express' => [
            'booking_to_pickup'     => 4,
            'pickup_to_origin_hub'  => 8,
            'origin_processing'     => 12,
            'transit_domestic'      => 24,
            'transit_international' => 72,
            'customs_clearance'     => 24,
            'last_mile'             => 8,
            'total_domestic'        => 48,
            'total_international'   => 120,
        ],
        'standard' => [
            'booking_to_pickup'     => 12,
            'pickup_to_origin_hub'  => 24,
            'origin_processing'     => 24,
            'transit_domestic'      => 48,
            'transit_international' => 168,
            'customs_clearance'     => 48,
            'last_mile'             => 24,
            'total_domestic'        => 96,
            'total_international'   => 336,
        ],
        'economy' => [
            'booking_to_pickup'     => 24,
            'pickup_to_origin_hub'  => 48,
            'origin_processing'     => 48,
            'transit_domestic'      => 96,
            'transit_international' => 336,
            'customs_clearance'     => 72,
            'last_mile'             => 48,
            'total_domestic'        => 240,
            'total_international'   => 720,
        ],
    ];

    // ── Escalation thresholds (% of SLA elapsed) ─────────────
    protected array $escalationLevels = [
        ['threshold' => 0.75, 'level' => 'warning',  'notify' => ['agent']],
        ['threshold' => 0.90, 'level' => 'critical', 'notify' => ['agent', 'supervisor']],
        ['threshold' => 1.00, 'level' => 'breached', 'notify' => ['agent', 'supervisor', 'manager']],
        ['threshold' => 1.50, 'level' => 'severe',   'notify' => ['agent', 'supervisor', 'manager', 'director']],
    ];

    /**
     * Check SLA status for a shipment
     */
    public function checkSLA(Shipment $shipment): array
    {
        $serviceLevel = $shipment->service_level ?? 'standard';
        $targets = $this->slaTargets[$serviceLevel] ?? $this->slaTargets['standard'];
        $isInternational = $shipment->origin_country !== $shipment->destination_country;

        $currentPhase = $this->getCurrentPhase($shipment);
        $phaseKey = $this->getPhaseKey($currentPhase, $isInternational);
        $targetHours = $targets[$phaseKey] ?? 48;

        $phaseStartTime = $this->getPhaseStartTime($shipment, $currentPhase);
        $elapsedHours = $phaseStartTime ? now()->diffInMinutes($phaseStartTime) / 60 : 0;
        $remainingHours = max(0, $targetHours - $elapsedHours);
        $percentage = $targetHours > 0 ? ($elapsedHours / $targetHours) : 0;

        // Total SLA
        $totalTarget = $isInternational ? $targets['total_international'] : $targets['total_domestic'];
        $totalElapsed = $shipment->created_at ? now()->diffInMinutes($shipment->created_at) / 60 : 0;
        $totalPercentage = $totalTarget > 0 ? ($totalElapsed / $totalTarget) : 0;

        $escalation = $this->getEscalationLevel($percentage);

        return [
            'shipment_id' => $shipment->id,
            'service_level' => $serviceLevel,
            'current_phase' => $currentPhase,
            'is_international' => $isInternational,
            'phase_sla' => [
                'target_hours' => $targetHours,
                'elapsed_hours' => round($elapsedHours, 1),
                'remaining_hours' => round($remainingHours, 1),
                'percentage' => round($percentage * 100, 1),
                'on_track' => $percentage <= 1.0,
                'breached' => $percentage > 1.0,
            ],
            'total_sla' => [
                'target_hours' => $totalTarget,
                'elapsed_hours' => round($totalElapsed, 1),
                'remaining_hours' => round(max(0, $totalTarget - $totalElapsed), 1),
                'percentage' => round($totalPercentage * 100, 1),
                'on_track' => $totalPercentage <= 1.0,
                'breached' => $totalPercentage > 1.0,
            ],
            'escalation' => $escalation,
            'eta' => $shipment->eta,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check all active shipments for SLA breaches
     */
    public function scanBreaches(): array
    {
        $activeStatuses = ['booked', 'picked_up', 'at_origin_hub', 'export_clearance',
            'in_transit', 'at_destination_hub', 'import_clearance', 'out_for_delivery'];

        $shipments = Shipment::whereIn('status', $activeStatuses)->get();
        $breaches = [];

        foreach ($shipments as $shipment) {
            $sla = $this->checkSLA($shipment);
            if ($sla['phase_sla']['breached'] || $sla['total_sla']['breached']) {
                $breaches[] = $sla;
                $this->createBreachRecord($shipment, $sla);
            }
        }

        return [
            'total_checked' => $shipments->count(),
            'breaches_found' => count($breaches),
            'breaches' => $breaches,
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get SLA report / dashboard data
     */
    public function dashboard(string $accountId): array
    {
        $shipments = Shipment::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $total = $shipments->count();
        $delivered = $shipments->where('status', 'delivered');
        $onTime = 0;
        $late = 0;

        foreach ($delivered as $s) {
            $sla = $this->checkSLA($s);
            if ($sla['total_sla']['on_track']) $onTime++;
            else $late++;
        }

        $active = $shipments->whereNotIn('status', ['delivered', 'cancelled', 'returned']);
        $atRisk = 0;
        foreach ($active as $s) {
            $sla = $this->checkSLA($s);
            if ($sla['phase_sla']['percentage'] > 75) $atRisk++;
        }

        return [
            'period' => '30_days',
            'total_shipments' => $total,
            'delivered' => $delivered->count(),
            'on_time' => $onTime,
            'late' => $late,
            'on_time_rate' => $delivered->count() > 0 ? round(($onTime / $delivered->count()) * 100, 1) : 0,
            'active' => $active->count(),
            'at_risk' => $atRisk,
            'by_service_level' => [
                'express' => $shipments->where('service_level', 'express')->count(),
                'standard' => $shipments->where('service_level', 'standard')->count(),
                'economy' => $shipments->where('service_level', 'economy')->count(),
            ],
        ];
    }

    protected function getCurrentPhase(Shipment $s): string
    {
        return match ($s->status) {
            'created', 'booked' => 'booking_to_pickup',
            'picked_up' => 'pickup_to_origin_hub',
            'at_origin_hub', 'export_clearance', 'cleared_export' => 'origin_processing',
            'in_transit' => 'transit',
            'at_destination_hub', 'import_clearance', 'cleared_import' => 'customs_clearance',
            'out_for_delivery', 'failed_delivery' => 'last_mile',
            default => 'unknown',
        };
    }

    protected function getPhaseKey(string $phase, bool $intl): string
    {
        if ($phase === 'transit') return $intl ? 'transit_international' : 'transit_domestic';
        return $phase;
    }

    protected function getPhaseStartTime(Shipment $s, string $phase): ?Carbon
    {
        return $s->status_updated_at ? Carbon::parse($s->status_updated_at) : $s->created_at;
    }

    protected function getEscalationLevel(float $percentage): ?array
    {
        $escalation = null;
        foreach ($this->escalationLevels as $level) {
            if ($percentage >= $level['threshold']) {
                $escalation = $level;
            }
        }
        return $escalation;
    }

    protected function createBreachRecord(Shipment $shipment, array $sla): void
    {
        ShipmentException::updateOrCreate(
            ['shipment_id' => $shipment->id, 'exception_type' => 'sla_breach'],
            [
                'id' => Str::uuid(),
                'description' => "SLA breach: {$sla['phase_sla']['percentage']}% of phase target elapsed",
                'severity' => $sla['escalation']['level'] ?? 'warning',
                'resolved' => false,
                'metadata' => $sla,
            ]
        );
    }
}
