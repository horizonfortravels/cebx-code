<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\ShipmentStatusHistory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CBEX GROUP — Status Transition Engine
 *
 * State machine for shipment lifecycle:
 * created → booked → picked_up → at_origin_hub → export_clearance →
 * in_transit → at_destination_hub → import_clearance → out_for_delivery →
 * delivered | returned | cancelled | exception
 */
class StatusTransitionService
{
    // ── Valid transitions map ─────────────────────────────────
    protected array $transitions = [
        'created'             => ['booked', 'cancelled'],
        'booked'              => ['picked_up', 'at_origin_hub', 'cancelled'],
        'picked_up'           => ['at_origin_hub', 'exception'],
        'at_origin_hub'       => ['export_clearance', 'in_transit', 'exception'],
        'export_clearance'    => ['cleared_export', 'held_customs', 'exception'],
        'cleared_export'      => ['in_transit', 'exception'],
        'held_customs'        => ['cleared_export', 'returned', 'exception'],
        'in_transit'          => ['at_destination_hub', 'in_transit', 'exception'],
        'at_destination_hub'  => ['import_clearance', 'exception'],
        'import_clearance'    => ['cleared_import', 'held_customs_dest', 'exception'],
        'cleared_import'      => ['out_for_delivery', 'ready_for_pickup', 'exception'],
        'held_customs_dest'   => ['cleared_import', 'returned', 'exception'],
        'out_for_delivery'    => ['delivered', 'failed_delivery', 'exception'],
        'failed_delivery'     => ['out_for_delivery', 'returned', 'exception'],
        'ready_for_pickup'    => ['delivered', 'returned', 'exception'],
        'delivered'           => ['claim_opened'],
        'returned'            => [],
        'cancelled'           => [],
        'exception'           => ['at_origin_hub', 'in_transit', 'at_destination_hub', 'returned', 'cancelled'],
        'claim_opened'        => ['claim_resolved'],
        'claim_resolved'      => [],
    ];

    // ── Status metadata ──────────────────────────────────────
    protected array $statusMeta = [
        'created'             => ['phase' => 'booking',      'label_ar' => 'تم الإنشاء',              'label_en' => 'Created'],
        'booked'              => ['phase' => 'booking',      'label_ar' => 'تم الحجز',                'label_en' => 'Booked'],
        'picked_up'           => ['phase' => 'origin',       'label_ar' => 'تم الاستلام',              'label_en' => 'Picked Up'],
        'at_origin_hub'       => ['phase' => 'origin',       'label_ar' => 'في مركز الأصل',           'label_en' => 'At Origin Hub'],
        'export_clearance'    => ['phase' => 'origin',       'label_ar' => 'تخليص تصدير',             'label_en' => 'Export Clearance'],
        'cleared_export'      => ['phase' => 'origin',       'label_ar' => 'تم تخليص التصدير',        'label_en' => 'Export Cleared'],
        'held_customs'        => ['phase' => 'origin',       'label_ar' => 'محتجز بالجمارك',          'label_en' => 'Held by Customs'],
        'in_transit'          => ['phase' => 'transit',      'label_ar' => 'قيد الشحن',               'label_en' => 'In Transit'],
        'at_destination_hub'  => ['phase' => 'destination',  'label_ar' => 'في مركز الوجهة',          'label_en' => 'At Destination Hub'],
        'import_clearance'    => ['phase' => 'destination',  'label_ar' => 'تخليص استيراد',           'label_en' => 'Import Clearance'],
        'cleared_import'      => ['phase' => 'destination',  'label_ar' => 'تم تخليص الاستيراد',      'label_en' => 'Import Cleared'],
        'held_customs_dest'   => ['phase' => 'destination',  'label_ar' => 'محتجز بجمارك الوجهة',    'label_en' => 'Held at Dest Customs'],
        'out_for_delivery'    => ['phase' => 'last_mile',    'label_ar' => 'خارج للتسليم',            'label_en' => 'Out for Delivery'],
        'failed_delivery'     => ['phase' => 'last_mile',    'label_ar' => 'فشل التسليم',             'label_en' => 'Failed Delivery'],
        'ready_for_pickup'    => ['phase' => 'last_mile',    'label_ar' => 'جاهز للاستلام',           'label_en' => 'Ready for Pickup'],
        'delivered'           => ['phase' => 'completed',    'label_ar' => 'تم التسليم',              'label_en' => 'Delivered'],
        'returned'            => ['phase' => 'completed',    'label_ar' => 'مرتجع',                   'label_en' => 'Returned'],
        'cancelled'           => ['phase' => 'completed',    'label_ar' => 'ملغي',                    'label_en' => 'Cancelled'],
        'exception'           => ['phase' => 'exception',    'label_ar' => 'استثناء',                 'label_en' => 'Exception'],
        'claim_opened'        => ['phase' => 'post',         'label_ar' => 'مطالبة مفتوحة',           'label_en' => 'Claim Opened'],
        'claim_resolved'      => ['phase' => 'post',         'label_ar' => 'مطالبة محلولة',           'label_en' => 'Claim Resolved'],
    ];

    /**
     * Transition shipment to a new status
     */
    public function transition(Shipment $shipment, string $newStatus, array $context = []): Shipment
    {
        $currentStatus = $shipment->status;

        // Validate transition is allowed
        if (!$this->canTransition($currentStatus, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن الانتقال من '{$currentStatus}' إلى '{$newStatus}'"],
            ]);
        }

        $oldStatus = $currentStatus;

        // Update shipment status
        $shipment->update([
            'status' => $newStatus,
            'status_updated_at' => now(),
        ]);

        // Log status history
        ShipmentStatusHistory::create([
            'id' => Str::uuid(),
            'shipment_id' => $shipment->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $context['user_id'] ?? auth()->id(),
            'notes' => $context['notes'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'created_at' => now(),
        ]);

        // Create tracking event
        TrackingEvent::create([
            'id' => Str::uuid(),
            'shipment_id' => $shipment->id,
            'status' => $newStatus,
            'description' => $this->getStatusLabel($newStatus, 'ar'),
            'location' => $context['location'] ?? null,
            'branch_id' => $context['branch_id'] ?? null,
            'notes' => $context['notes'] ?? null,
            'event_time' => now(),
        ]);

        // Trigger phase-specific actions
        $this->handlePhaseActions($shipment, $newStatus, $context);

        return $shipment->fresh();
    }

    /**
     * Check if transition is valid
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->transitions[$from] ?? []);
    }

    /**
     * Get all valid next statuses for current status
     */
    public function getNextStatuses(string $currentStatus): array
    {
        $nextStatuses = $this->transitions[$currentStatus] ?? [];
        return array_map(function ($s) {
            return [
                'status' => $s,
                'label_ar' => $this->getStatusLabel($s, 'ar'),
                'label_en' => $this->getStatusLabel($s, 'en'),
                'phase' => $this->statusMeta[$s]['phase'] ?? 'unknown',
            ];
        }, $nextStatuses);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(string $status, string $lang = 'ar'): string
    {
        $key = $lang === 'ar' ? 'label_ar' : 'label_en';
        return $this->statusMeta[$status][$key] ?? $status;
    }

    /**
     * Get all statuses with metadata
     */
    public function getAllStatuses(): array
    {
        return array_map(fn($meta, $status) => array_merge(
            ['status' => $status],
            $meta,
            ['next_statuses' => $this->transitions[$status] ?? []]
        ), $this->statusMeta, array_keys($this->statusMeta));
    }

    /**
     * Get status phase
     */
    public function getPhase(string $status): string
    {
        return $this->statusMeta[$status]['phase'] ?? 'unknown';
    }

    /**
     * Handle actions based on the new status phase
     */
    protected function handlePhaseActions(Shipment $shipment, string $newStatus, array $context): void
    {
        $phase = $this->getPhase($newStatus);

        match ($newStatus) {
            'booked' => $this->onBooked($shipment, $context),
            'in_transit' => $this->onInTransit($shipment, $context),
            'at_destination_hub' => $this->onArrival($shipment, $context),
            'import_clearance' => $this->onImportClearance($shipment, $context),
            'out_for_delivery' => $this->onOutForDelivery($shipment, $context),
            'delivered' => $this->onDelivered($shipment, $context),
            'cancelled' => $this->onCancelled($shipment, $context),
            'exception' => $this->onException($shipment, $context),
            default => null,
        };
    }

    protected function onBooked(Shipment $shipment, array $ctx): void
    {
        // Generate invoice, reserve wallet balance
        // app(PaymentService::class)->createInvoice($shipment);
    }

    protected function onInTransit(Shipment $shipment, array $ctx): void
    {
        // Update ETA, notify customer
        if (isset($ctx['vessel_id'])) {
            $shipment->update(['vessel_id' => $ctx['vessel_id']]);
        }
    }

    protected function onArrival(Shipment $shipment, array $ctx): void
    {
        // Trigger import customs process
    }

    protected function onImportClearance(Shipment $shipment, array $ctx): void
    {
        // Calculate duties
        // app(DutyCalculationService::class)->calculate(...)
    }

    protected function onOutForDelivery(Shipment $shipment, array $ctx): void
    {
        // Assign driver, start SLA timer
        // app(SLAEngineService::class)->startDeliveryTimer($shipment);
    }

    protected function onDelivered(Shipment $shipment, array $ctx): void
    {
        // Close invoice, record revenue, update branch stats
        $shipment->update(['delivered_at' => now()]);
    }

    protected function onCancelled(Shipment $shipment, array $ctx): void
    {
        // Refund if applicable, release wallet hold
        $shipment->update(['cancelled_at' => now()]);
    }

    protected function onException(Shipment $shipment, array $ctx): void
    {
        // Create exception record, alert operations
    }
}
