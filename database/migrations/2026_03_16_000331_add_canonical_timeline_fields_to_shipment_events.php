<?php

use App\Support\CanonicalShipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipment_events')) {
            return;
        }

        Schema::table('shipment_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipment_events', 'account_id')) {
                $table->uuid('account_id')->nullable()->after('shipment_id');
                $table->index('account_id');
            }

            if (! Schema::hasColumn('shipment_events', 'normalized_status')) {
                $table->string('normalized_status', 50)->nullable()->after('event_type');
                $table->index('normalized_status');
            }

            if (! Schema::hasColumn('shipment_events', 'source')) {
                $table->string('source', 20)->nullable()->after('event_at');
                $table->index('source');
            }
        });

        DB::table('shipment_events')
            ->select('shipment_events.id', 'shipment_events.event_type', 'shipment_events.status', 'shipments.account_id')
            ->join('shipments', 'shipments.id', '=', 'shipment_events.shipment_id')
            ->orderBy('shipment_events.created_at')
            ->get()
            ->each(function (object $row): void {
                DB::table('shipment_events')
                    ->where('id', $row->id)
                    ->update([
                        'account_id' => $row->account_id,
                        'normalized_status' => $this->resolveNormalizedStatus(
                            (string) ($row->event_type ?? ''),
                            (string) ($row->status ?? '')
                        ),
                        'source' => $this->resolveSource((string) ($row->event_type ?? '')),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipment_events')) {
            return;
        }

        Schema::table('shipment_events', function (Blueprint $table): void {
            if (Schema::hasColumn('shipment_events', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('shipment_events', 'normalized_status')) {
                $table->dropIndex(['normalized_status']);
                $table->dropColumn('normalized_status');
            }

            if (Schema::hasColumn('shipment_events', 'account_id')) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }

    private function resolveNormalizedStatus(string $eventType, string $status): ?string
    {
        $eventType = strtolower(trim($eventType));

        if ($eventType === 'carrier.documents_available') {
            return CanonicalShipmentStatus::LABEL_READY;
        }

        if ($eventType === 'shipment.purchased') {
            return CanonicalShipmentStatus::PURCHASED;
        }

        if ($eventType === 'shipment.cancelled') {
            return CanonicalShipmentStatus::CANCELLED;
        }

        $resolved = CanonicalShipmentStatus::normalize($status);

        return $resolved === CanonicalShipmentStatus::UNKNOWN ? null : $resolved;
    }

    private function resolveSource(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            'carrier.documents_available',
            'tracking.status_updated' => 'carrier',
            default => 'system',
        };
    }
};
