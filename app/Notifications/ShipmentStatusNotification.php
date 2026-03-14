<?php
namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ShipmentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Shipment $shipment,
        private string $oldStatus,
        private string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("تحديث الشحنة {$this->shipment->tracking_number}")
            ->greeting("مرحباً {$notifiable->name}")
            ->line("تم تحديث حالة الشحنة {$this->shipment->tracking_number}")
            ->line("الحالة الجديدة: {$this->newStatus}")
            ->action('عرض الشحنة', url("/shipments/{$this->shipment->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shipment_id' => $this->shipment->id,
            'tracking_number' => $this->shipment->tracking_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'carrier' => $this->shipment->carrier,
        ];
    }
}
