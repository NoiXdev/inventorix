<?php

namespace App\Mail;

use App\Models\CheckoutAcceptance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the IT team that a user accepted or declined an item. */
class AcceptanceResponse extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CheckoutAcceptance $acceptance) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans($this->acceptance->accepted_at ? 'acceptance.mail.accepted_subject' : 'acceptance.mail.declined_subject', [
            'user' => $this->acceptance->user?->name ?? '—',
            'item' => $this->acceptance->item_name,
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.acceptance-response');
    }
}
