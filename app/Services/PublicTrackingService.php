<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Support\CanonicalShipmentStatus;
use App\Support\PortalShipmentLabeler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicTrackingService
{
    public function __construct(
        private ShipmentTimelineService $shipmentTimeline,
    ) {}

    public function publicUrl(Shipment $shipment): ?string
    {
        $token = $this->ensureToken($shipment);

        if ($token === null) {
            return null;
        }

        return route('public.tracking.show', ['token' => $token]);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function resolveShipment(string $token): Shipment
    {
        if (! $this->supportsPublicTracking()) {
            throw (new ModelNotFoundException())->setModel(Shipment::class);
        }

        $hashedToken = hash('sha256', trim($token));
        $shipment = Shipment::query()
            ->with(['carrierShipment'])
            ->where('public_tracking_token_hash', $hashedToken)
            ->first();

        if (! $shipment instanceof Shipment) {
            throw (new ModelNotFoundException())->setModel(Shipment::class);
        }

        if (! $this->isPubliclyTrackable($shipment) || $this->isExpired($shipment)) {
            throw (new ModelNotFoundException())->setModel(Shipment::class);
        }

        return $shipment;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Shipment $shipment): array
    {
        $currentStatus = $this->shipmentTimeline->currentStatus($shipment);
        $events = collect($this->shipmentTimeline->list($shipment))
            ->map(fn (ShipmentEvent $event): ?array => $this->presentEvent($event))
            ->filter()
            ->sortByDesc('event_time')
            ->values();

        return [
            'current_status' => $currentStatus,
            'current_status_label' => PortalShipmentLabeler::status(
                $currentStatus,
                CanonicalShipmentStatus::label($currentStatus)
            ),
            'last_updated' => $events->first()['event_time'] ?? optional($shipment->tracking_updated_at)?->toIso8601String(),
            'events_count' => $events->count(),
            'carrier_name' => (string) ($shipment->carrierShipment?->carrier_name
                ?? $shipment->carrier_name
                ?? PortalShipmentLabeler::carrier(
                    (string) ($shipment->carrierShipment?->carrier_code ?? $shipment->carrier_code ?? ''),
                    ''
                )),
            'tracking_number_masked' => $this->maskIdentifier($this->trackingNumber($shipment)),
            'origin_summary' => $this->coarseLocation(
                (string) ($shipment->sender_city ?? ''),
                (string) ($shipment->sender_country ?? '')
            ),
            'destination_summary' => $this->coarseLocation(
                (string) ($shipment->recipient_city ?? ''),
                (string) ($shipment->recipient_country ?? '')
            ),
            'events' => $events->all(),
        ];
    }

    public function apiPayload(string $token): array
    {
        return $this->present($this->resolveShipment($token));
    }

    public function ensureToken(Shipment $shipment): ?string
    {
        if (! $this->supportsPublicTracking() || ! $this->isPubliclyTrackable($shipment)) {
            return null;
        }

        $existingToken = trim((string) ($shipment->public_tracking_token ?? ''));
        $existingHash = trim((string) ($shipment->public_tracking_token_hash ?? ''));

        if ($existingToken !== '' && $existingHash !== '') {
            return $existingToken;
        }

        if ($existingToken !== '' && $existingHash === '') {
            $shipment->forceFill([
                'public_tracking_token_hash' => hash('sha256', $existingToken),
                'public_tracking_enabled_at' => $shipment->public_tracking_enabled_at ?? now(),
            ])->save();

            return $existingToken;
        }

        $token = Str::random(48);
        $shipment->forceFill([
            'public_tracking_token' => $token,
            'public_tracking_token_hash' => hash('sha256', $token),
            'public_tracking_enabled_at' => $shipment->public_tracking_enabled_at ?? now(),
        ])->save();

        return $token;
    }

    private function supportsPublicTracking(): bool
    {
        return Schema::hasColumns('shipments', [
            'public_tracking_token',
            'public_tracking_token_hash',
        ]);
    }

    private function isPubliclyTrackable(Shipment $shipment): bool
    {
        if ($this->trackingNumber($shipment) !== '') {
            return true;
        }

        return in_array((string) $shipment->status, [
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP,
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
            Shipment::STATUS_DELIVERED,
            Shipment::STATUS_RETURNED,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_CANCELLED,
        ], true);
    }

    private function isExpired(Shipment $shipment): bool
    {
        $expiresAt = $shipment->public_tracking_expires_at;

        return $expiresAt instanceof Carbon && $expiresAt->isPast();
    }

    private function trackingNumber(Shipment $shipment): string
    {
        return trim((string) ($shipment->tracking_number ?? $shipment->carrierShipment?->tracking_number ?? $shipment->carrier_tracking_number ?? ''));
    }

    /**
     * @return array<string, string>|null
     */
    private function presentEvent(ShipmentEvent $event): ?array
    {
        $item = $event->toTimelineItem();
        $eventType = (string) ($item['event_type'] ?? '');
        $status = CanonicalShipmentStatus::normalize((string) ($item['status'] ?? ''));

        if (! $this->isPublicEvent($eventType, $status)) {
            return null;
        }

        $statusLabel = PortalShipmentLabeler::status($status, (string) ($item['status_label'] ?? ''));
        $title = $eventType === 'tracking.status_updated'
            ? $statusLabel
            : PortalShipmentLabeler::event($eventType, (string) ($item['event_type_label'] ?? ''));

        $location = $this->coarseEventLocation($event);

        return array_filter([
            'event_time' => (string) ($item['event_time'] ?? ''),
            'status' => $status,
            'status_label' => $statusLabel,
            'title' => $title,
            'location' => $location,
        ], static fn ($value): bool => $value !== '');
    }

    private function isPublicEvent(string $eventType, string $status): bool
    {
        if ($eventType === 'tracking.status_updated') {
            return in_array($status, [
                CanonicalShipmentStatus::PURCHASED,
                CanonicalShipmentStatus::LABEL_READY,
                CanonicalShipmentStatus::IN_TRANSIT,
                CanonicalShipmentStatus::OUT_FOR_DELIVERY,
                CanonicalShipmentStatus::DELIVERED,
                CanonicalShipmentStatus::EXCEPTION,
                CanonicalShipmentStatus::RETURNED,
                CanonicalShipmentStatus::CANCELLED,
            ], true);
        }

        return in_array($eventType, [
            'shipment.purchased',
            'carrier.documents_available',
        ], true);
    }

    private function coarseEventLocation(ShipmentEvent $event): string
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        $city = trim((string) ($payload['location_city'] ?? ''));
        $country = trim((string) ($payload['location_country'] ?? ''));

        if ($city === '' && $country === '') {
            return '';
        }

        return $this->coarseLocation($city, $country);
    }

    private function coarseLocation(string $city, string $country): string
    {
        return collect([$city, $country])
            ->map(static fn (string $value): string => trim($value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->implode(', ');
    }

    private function maskIdentifier(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return __('public_tracking.common.not_available');
        }

        $length = strlen($normalized);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $prefix = substr($normalized, 0, min(3, max(1, $length - 4)));
        $suffix = substr($normalized, -4);
        $masked = str_repeat('*', max(0, $length - strlen($prefix) - 4));

        return $prefix . $masked . $suffix;
    }
}
