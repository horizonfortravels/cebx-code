<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class KycStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $status,
        private ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)->subject('تحديث حالة التحقق KYC');
        if ($this->status === 'approved') {
            $msg->greeting('تهانينا!')->line('تم قبول وثائق التحقق الخاصة بك.');
        } else {
            $msg->greeting('تحديث مهم')->line('يحتاج ملفك تعديلاً.')->line("السبب: {$this->reason}");
        }
        return $msg->action('عرض حالة KYC', url('/kyc'));
    }

    public function toArray(object $notifiable): array
    {
        return ['status' => $this->status, 'reason' => $this->reason];
    }
}
