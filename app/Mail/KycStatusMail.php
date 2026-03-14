<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KycStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $accountName,
        public string $status,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $s = $this->status === 'approved' ? 'مقبول' : 'يحتاج تعديل';
        return new Envelope(subject: "تحديث حالة التحقق KYC — {$s}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.kyc-status');
    }
}
