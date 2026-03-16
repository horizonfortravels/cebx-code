<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\TrackingEvent;
use App\Support\CanonicalShipmentStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ShipmentTimelineService
{
    public function record(Shipment $shipment, array $attributes): ShipmentEvent
    {
        $idempotencyKey = $this->nullableString($attributes['idempotency_key'] ?? null);

        if ($idempotencyKey !== null) {
            $existing = ShipmentEvent::query()
                ->where('shipment_id', (string) $shipment->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof ShipmentEvent) {
                return $existing;
            }
        }

        return ShipmentEvent::query()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $shipment->account_id,
            'event_type' => $this->nullableString($attributes['event_type'] ?? null) ?? 'shipment.updated',
            'status' => $this->nullableString($attributes['status'] ?? $attributes['normalized_status'] ?? null),
            'normalized_status' => $this->resolveNormalizedStatus($attributes['normalized_status'] ?? $attributes['status'] ?? null),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'location' => $this->nullableString($attributes['location'] ?? null),
            'event_at' => $this->resolveEventTime($attributes['event_at'] ?? $attributes['event_time'] ?? null),
            'source' => $this->nullableString($attributes['source'] ?? null) ?? ShipmentEvent::SOURCE_SYSTEM,
            'correlation_id' => $this->nullableString($attributes['correlation_id'] ?? null),
            'idempotency_key' => $idempotencyKey,
            'payload' => $attributes['payload'] ?? $attributes['metadata'] ?? null,
        ]);
    }

    public function syncCurrentStatus(Shipment $shipment, ?string $status, CarbonInterface|string|null $eventAt = null): void
    {
        $normalizedStatus = $this->resolveNormalizedStatus($status);

        if ($normalizedStatus === null || $normalizedStatus === CanonicalShipmentStatus::UNKNOWN) {
            return;
        }

        $resolvedEventAt = $this->resolveEventTime($eventAt);
        $currentUpdatedAt = $shipment->tracking_updated_at;

        if ($currentUpdatedAt instanceof CarbonInterface && $resolvedEventAt->lt($currentUpdatedAt)) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn('shipments', 'tracking_status')) {
            $updates['tracking_status'] = $normalizedStatus;
        }

        if (Schema::hasColumn('shipments', 'tracking_updated_at')) {
            $updates['tracking_updated_at'] = $resolvedEventAt;
        }

        if ($normalizedStatus === CanonicalShipmentStatus::DELIVERED) {
            if (Schema::hasColumn('shipments', 'delivered_at')) {
                $updates['delivered_at'] = $resolvedEventAt;
            }

            if (Schema::hasColumn('shipments', 'actual_delivery_at')) {
                $updates['actual_delivery_at'] = $resolvedEventAt;
            }
        }

        if ($updates !== []) {
            $shipment->forceFill($updates)->save();
        }
    }

    public function currentStatus(Shipment $shipment): string
    {
        $latestTimelineEvent = ShipmentEvent::query()
            ->where('shipment_id', (string) $shipment->id)
            ->whereNotNull('normalized_status')
            ->where('normalized_status', '!=', CanonicalShipmentStatus::UNKNOWN)
            ->orderByDesc('event_at')
            ->orderByDesc('created_at')
            ->first();

        if ($latestTimelineEvent instanceof ShipmentEvent) {
            return CanonicalShipmentStatus::normalize($latestTimelineEvent->normalized_status);
        }

        return CanonicalShipmentStatus::fromShipment($shipment->loadMissing('carrierShipment'));
    }

    /**
     * @return Collection<int, ShipmentEvent>
     */
    public function list(Shipment $shipment): Collection
    {
        $events = ShipmentEvent::query()
            ->where('shipment_id', (string) $shipment->id)
            ->orderBy('event_at')
            ->orderBy('created_at')
            ->get();

        if ($events->isNotEmpty()) {
            return $events;
        }

        return $this->legacyTrackingEvents($shipment);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Shipment $shipment): array
    {
        $events = $this->list($shipment)
            ->map(static fn (ShipmentEvent $event): array => $event->toTimelineItem())
            ->values();

        $lastUpdated = $events->last()['event_time'] ?? optional($shipment->tracking_updated_at)?->toIso8601String();
        $currentStatus = $this->currentStatus($shipment);

        return [
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $shipment->account_id,
            'tracking_number' => (string) ($shipment->tracking_number ?? $shipment->carrier_tracking_number ?? ''),
            'current_status' => $currentStatus,
            'current_status_label' => CanonicalShipmentStatus::label($currentStatus),
            'last_updated' => $lastUpdated,
            'total_events' => $events->count(),
            'events' => $events->all(),
        ];
    }

    public function recordTrackingEvent(Shipment $shipment, TrackingEvent $trackingEvent): ShipmentEvent
    {
        return $this->record($shipment, [
            'event_type' => 'tracking.status_updated',
            'status' => $trackingEvent->unified_status,
            'normalized_status' => $trackingEvent->unified_status,
            'description' => $trackingEvent->unified_description ?? $trackingEvent->raw_description,
            'location' => trim(implode(' - ', array_filter([
                $trackingEvent->location_city,
                $trackingEvent->location_country,
            ]))) ?: null,
            'event_at' => $trackingEvent->event_time,
            'source' => ShipmentEvent::SOURCE_CARRIER,
            'correlation_id' => $this->nullableString($trackingEvent->webhook_id),
            'idempotency_key' => $trackingEvent->dedup_key,
            'payload' => array_filter([
                'tracking_event_id' => (string) $trackingEvent->id,
                'carrier_code' => (string) $trackingEvent->carrier_code,
                'tracking_number' => (string) $trackingEvent->tracking_number,
                'raw_status' => (string) $trackingEvent->raw_status,
                'raw_status_code' => (string) $trackingEvent->raw_status_code,
                'raw_description' => $trackingEvent->raw_description,
                'unified_description' => $trackingEvent->unified_description,
                'signatory' => $trackingEvent->signatory,
                'location_city' => $trackingEvent->location_city,
                'location_country' => $trackingEvent->location_country,
                'location_code' => $trackingEvent->location_code,
                'location_description' => $trackingEvent->location_description,
                'source' => $trackingEvent->source,
                'is_exception' => (bool) $trackingEvent->is_exception,
                'raw_payload' => $trackingEvent->raw_payload,
            ], static fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    /**
     * @return Collection<int, ShipmentEvent>
     */
    private function legacyTrackingEvents(Shipment $shipment): Collection
    {
        return TrackingEvent::query()
            ->where('shipment_id', (string) $shipment->id)
            ->orderBy('event_time')
            ->get()
            ->map(function (TrackingEvent $trackingEvent) use ($shipment): ShipmentEvent {
                return ShipmentEvent::make([
                    'shipment_id' => (string) $shipment->id,
                    'account_id' => (string) $shipment->account_id,
                    'event_type' => 'tracking.status_updated',
                    'status' => $trackingEvent->unified_status,
                    'normalized_status' => $trackingEvent->unified_status,
                    'description' => $trackingEvent->unified_description ?? $trackingEvent->raw_description,
                    'location' => trim(implode(' - ', array_filter([
                        $trackingEvent->location_city,
                        $trackingEvent->location_country,
                    ]))) ?: null,
                    'event_at' => $trackingEvent->event_time,
                    'source' => ShipmentEvent::SOURCE_CARRIER,
                    'correlation_id' => $this->nullableString($trackingEvent->webhook_id),
                    'idempotency_key' => $trackingEvent->dedup_key,
                    'payload' => [
                        'tracking_event_id' => (string) $trackingEvent->id,
                        'raw_status' => $trackingEvent->raw_status,
                        'raw_status_code' => $trackingEvent->raw_status_code,
                        'signatory' => $trackingEvent->signatory,
                    ],
                ]);
            })
            ->values();
    }

    private function resolveNormalizedStatus(?string $status): ?string
    {
        $value = $this->nullableString($status);

        return $value === null ? null : CanonicalShipmentStatus::normalize($value);
    }

    private function resolveEventTime(CarbonInterface|string|null $eventAt): CarbonInterface
    {
        if ($eventAt instanceof CarbonInterface) {
            return $eventAt;
        }

        if (is_string($eventAt) && trim($eventAt) !== '') {
            return Carbon::parse($eventAt);
        }

        return now();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $resolved = trim((string) $value);

        return $resolved === '' ? null : $resolved;
    }
}
