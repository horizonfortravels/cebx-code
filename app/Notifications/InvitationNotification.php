<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $organizationName,
        private string $roleName,
        private string $acceptUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("دعوة للانضمام إلى {$this->organizationName}")
            ->greeting('مرحباً!')
            ->line("تمت دعوتك للانضمام إلى {$this->organizationName} بدور {$this->roleName}.")
            ->action('قبول الدعوة', $this->acceptUrl)
            ->line('هذه الدعوة صالحة لمدة 7 أيام.');
    }
}
