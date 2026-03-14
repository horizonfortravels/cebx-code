<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $roleName,
        public string $inviterName,
        public string $organizationName,
        public string $acceptUrl,
        public string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "دعوة للانضمام إلى {$this->organizationName} — بوابة الشحن",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation');
    }
}
