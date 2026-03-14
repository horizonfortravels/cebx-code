<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TicketReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $ticketId,
        private string $subject,
        private string $replierName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("رد جديد على التذكرة: {$this->subject}")
            ->greeting("مرحباً {$notifiable->name}")
            ->line("{$this->replierName} أضاف رداً على تذكرتك.")
            ->action('عرض التذكرة', url("/support/tickets/{$this->ticketId}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'subject' => $this->subject,
            'replier' => $this->replierName,
        ];
    }
}
