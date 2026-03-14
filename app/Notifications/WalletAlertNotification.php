<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WalletAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private float $balance,
        private float $threshold,
        private string $type = 'low_balance',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تنبيه: رصيد المحفظة منخفض')
            ->greeting("مرحباً {$notifiable->name}")
            ->line("رصيد محفظتك الحالي: {$this->balance} ر.س")
            ->line("الحد الأدنى المطلوب: {$this->threshold} ر.س")
            ->action('شحن المحفظة', url('/wallet'))
            ->line('يرجى شحن المحفظة لتجنب توقف الخدمة.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'balance' => $this->balance,
            'threshold' => $this->threshold,
        ];
    }
}
