<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Support\CanonicalShipmentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ShipmentNotificationFanoutService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @return array<int, \App\Models\Notification>
     */
    public function fanout(Shipment $shipment, ShipmentEvent $event): array
    {
        $notificationEvent = $this->resolveNotificationEvent($event);
        if ($notificationEvent === null) {
            return [];
        }

        $shipment->loadMissing('account');
        if ($shipment->account === null) {
            return [];
        }

        $recipientIds = $this->resolveRecipientIds($shipment);
        if ($recipientIds === []) {
            return [];
        }

        $eventData = $this->buildEventData($shipment, $event, $notificationEvent);

        $inApp = $this->notifications->storeInAppProjection(
            $notificationEvent,
            $shipment->account,
            $eventData,
            'shipment',
            (string) $shipment->id,
            $recipientIds,
            (string) $event->id
        );

        if (! $this->supportsEmailProjection($notificationEvent)) {
            return $inApp;
        }

        $email = $this->notifications->storeShipmentEmailProjection(
            $notificationEvent,
            $shipment->account,
            $eventData,
            'shipment',
            (string) $shipment->id,
            $recipientIds,
            (string) $event->id
        );

        return array_merge($inApp, $email);
    }

    private function resolveNotificationEvent(ShipmentEvent $event): ?string
    {
        $eventType = trim((string) $event->event_type);
        $normalizedStatus = CanonicalShipmentStatus::normalize((string) ($event->normalized_status ?? $event->status ?? ''));

        if ($eventType === 'shipment.purchased') {
            return Notification::EVENT_SHIPMENT_PURCHASED;
        }

        if ($eventType === 'carrier.documents_available') {
            return Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE;
        }

        if ($eventType !== 'tracking.status_updated') {
            return null;
        }

        return match ($normalizedStatus) {
            CanonicalShipmentStatus::IN_TRANSIT => Notification::EVENT_SHIPMENT_IN_TRANSIT,
            CanonicalShipmentStatus::OUT_FOR_DELIVERY => Notification::EVENT_SHIPMENT_OUT_FOR_DELIVERY,
            CanonicalShipmentStatus::DELIVERED => Notification::EVENT_SHIPMENT_DELIVERED,
            CanonicalShipmentStatus::EXCEPTION => Notification::EVENT_SHIPMENT_EXCEPTION,
            CanonicalShipmentStatus::RETURNED => Notification::EVENT_SHIPMENT_RETURNED,
            CanonicalShipmentStatus::CANCELLED => Notification::EVENT_SHIPMENT_CANCELLED,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecipientIds(Shipment $shipment): array
    {
        $query = User::query()
            ->where('account_id', (string) $shipment->account_id);

        if (Schema::hasColumn('users', 'user_type')) {
            $query->where('user_type', 'external');
        }

        if (Schema::hasColumn('users', 'status')) {
            $query->where('status', 'active');
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('created_at')
            ->pluck('id')
            ->filter(static fn ($id): bool => is_string($id) && trim($id) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventData(Shipment $shipment, ShipmentEvent $event, string $notificationEvent): array
    {
        $normalizedStatus = CanonicalShipmentStatus::normalize((string) ($event->normalized_status ?? $event->status ?? ''));
        $trackingNumber = trim((string) ($shipment->tracking_number ?? $shipment->carrier_tracking_number ?? ''));
        $payload = is_array($event->payload) ? $event->payload : [];
        $eventTime = $event->event_at?->toIso8601String();

        return array_filter([
            'shipment_id' => (string) $shipment->id,
            'shipment_reference' => (string) ($shipment->reference_number ?? ''),
            'shipment_status' => (string) ($shipment->status ?? ''),
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'carrier_code' => (string) ($shipment->carrier_code ?? data_get($payload, 'carrier_code', '')),
            'carrier_name' => (string) ($shipment->carrier_name ?? ''),
            'service_code' => (string) ($shipment->service_code ?? ''),
            'service_name' => (string) ($shipment->service_name ?? ''),
            'notification_event' => $notificationEvent,
            'timeline_event_type' => (string) $event->event_type,
            'timeline_event_id' => (string) $event->id,
            'normalized_status' => $normalizedStatus,
            'status_label' => CanonicalShipmentStatus::label($normalizedStatus),
            'event_description' => (string) ($event->description ?? $event->eventTypeLabel()),
            'event_time' => $eventTime,
            'source' => (string) ($event->source ?? ShipmentEvent::SOURCE_SYSTEM),
            'correlation_id' => $event->correlation_id,
            'payload' => $payload !== [] ? $payload : null,
            // Legacy browser surface still checks for a simple title-like field.
            'title' => $this->defaultTitle($notificationEvent, $event, $trackingNumber),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function defaultTitle(string $notificationEvent, ShipmentEvent $event, string $trackingNumber): string
    {
        $suffix = $trackingNumber !== '' ? ' ' . $trackingNumber : '';

        return match ($notificationEvent) {
            Notification::EVENT_SHIPMENT_PURCHASED => 'تم إصدار الشحنة' . $suffix,
            Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE => 'أصبحت مستندات الشحنة متاحة' . $suffix,
            Notification::EVENT_SHIPMENT_IN_TRANSIT => 'الشحنة في الطريق' . $suffix,
            Notification::EVENT_SHIPMENT_OUT_FOR_DELIVERY => 'خرجت الشحنة للتسليم' . $suffix,
            Notification::EVENT_SHIPMENT_DELIVERED => 'تم تسليم الشحنة' . $suffix,
            Notification::EVENT_SHIPMENT_EXCEPTION => 'يوجد استثناء على الشحنة' . $suffix,
            Notification::EVENT_SHIPMENT_CANCELLED => 'تم إلغاء الشحنة' . $suffix,
            Notification::EVENT_SHIPMENT_RETURNED => 'تمت إعادة الشحنة' . $suffix,
            default => (string) ($event->description ?? $event->eventTypeLabel()),
        };
    }

    private function supportsEmailProjection(string $notificationEvent): bool
    {
        return in_array($notificationEvent, [
            Notification::EVENT_SHIPMENT_PURCHASED,
            Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE,
        ], true);
    }
}
