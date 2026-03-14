<?php

namespace Database\Seeders;

use App\Models\StatusMapping;
use App\Models\TrackingEvent;
use Illuminate\Database\Seeder;

/**
 * Seeds the default DHL → Unified status mappings (FR-TR-004).
 */
class DhlStatusMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            ['carrier_status' => 'shipment_booked', 'code' => 'PU', 'unified' => TrackingEvent::STATUS_LABEL_CREATED, 'desc' => 'Label Created', 'store' => false, 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'picked_up', 'code' => 'PL', 'unified' => TrackingEvent::STATUS_PICKED_UP, 'desc' => 'Picked Up', 'store' => true, 'store_status' => 'in_transit', 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'transit', 'code' => 'DF', 'unified' => TrackingEvent::STATUS_IN_TRANSIT, 'desc' => 'In Transit', 'store' => false, 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'processed', 'code' => 'AF', 'unified' => TrackingEvent::STATUS_IN_TRANSIT, 'desc' => 'Processed at Facility', 'store' => false, 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'clearance', 'code' => 'CC', 'unified' => TrackingEvent::STATUS_CUSTOMS, 'desc' => 'In Customs', 'store' => true, 'store_status' => 'in_customs', 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'customs_released', 'code' => 'CR', 'unified' => TrackingEvent::STATUS_CUSTOMS_RELEASED, 'desc' => 'Customs Released', 'store' => false, 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'out_for_delivery', 'code' => 'WC', 'unified' => TrackingEvent::STATUS_OUT_FOR_DELIVERY, 'desc' => 'Out for Delivery', 'store' => true, 'store_status' => 'out_for_delivery', 'terminal' => false, 'exception' => false],
            ['carrier_status' => 'delivered', 'code' => 'OK', 'unified' => TrackingEvent::STATUS_DELIVERED, 'desc' => 'Delivered', 'store' => true, 'store_status' => 'fulfilled', 'terminal' => true, 'exception' => false],
            ['carrier_status' => 'exception', 'code' => 'NH', 'unified' => TrackingEvent::STATUS_EXCEPTION, 'desc' => 'Exception', 'store' => true, 'store_status' => 'exception', 'terminal' => false, 'exception' => true, 'action' => true],
            ['carrier_status' => 'delivery_failed', 'code' => 'BA', 'unified' => TrackingEvent::STATUS_FAILED_ATTEMPT, 'desc' => 'Delivery Failed', 'store' => true, 'store_status' => 'delivery_failed', 'terminal' => false, 'exception' => true, 'action' => true],
            ['carrier_status' => 'returned', 'code' => 'RT', 'unified' => TrackingEvent::STATUS_RETURNED, 'desc' => 'Returned to Sender', 'store' => true, 'store_status' => 'returned', 'terminal' => true, 'exception' => false],
            ['carrier_status' => 'on_hold', 'code' => 'OH', 'unified' => TrackingEvent::STATUS_ON_HOLD, 'desc' => 'On Hold', 'store' => true, 'store_status' => 'on_hold', 'terminal' => false, 'exception' => true, 'action' => true],
        ];

        foreach ($mappings as $i => $m) {
            StatusMapping::updateOrCreate(
                ['carrier_code' => 'dhl', 'carrier_status' => $m['carrier_status'], 'carrier_status_code' => $m['code']],
                [
                    'unified_status'      => $m['unified'],
                    'unified_description' => $m['desc'],
                    'notify_store'        => $m['store'],
                    'store_status'        => $m['store_status'] ?? null,
                    'is_terminal'         => $m['terminal'],
                    'is_exception'        => $m['exception'],
                    'requires_action'     => $m['action'] ?? false,
                    'sort_order'          => $i,
                    'is_active'           => true,
                ]
            );
        }
    }
}
