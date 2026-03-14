<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportName,
        public string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "تقريرك جاهز: {$this->reportName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.report-ready');
    }
}
