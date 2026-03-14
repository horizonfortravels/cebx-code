<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WalletLowBalanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $accountName,
        public float $balance,
        public float $threshold,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'تنبيه: رصيد المحفظة منخفض');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.wallet-low-balance');
    }
}
