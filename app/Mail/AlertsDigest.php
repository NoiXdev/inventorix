<?php

namespace App\Mail;

use App\Models\Asset;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** The daily overview of due and overdue check-ins and audits for the IT team. */
class AlertsDigest extends Mailable
{
    /** @param array<string, Collection<int, Asset>> $sections AssetDue value => assets, only non-empty ones */
    public function __construct(public array $sections) {}

    public function envelope(): Envelope
    {
        $count = array_sum(array_map(fn (Collection $c) => $c->count(), $this->sections));

        return new Envelope(subject: trans('alerts.digest.subject', ['count' => $count]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.alerts-digest', with: ['sections' => $this->sections]);
    }
}
