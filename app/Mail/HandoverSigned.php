<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HandoverSigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $handoverId) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('handover.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Handover ' . $this->handoverId . '</p>',
        );
    }
}
