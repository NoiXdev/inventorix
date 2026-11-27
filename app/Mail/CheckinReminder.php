<?php

namespace App\Mail;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Reminds a user that assets checked out to them are due back (Snipe-IT's expected check-in notification). */
class CheckinReminder extends Mailable
{
    /** @param Collection<int, Asset> $assets */
    public function __construct(public User $user, public Collection $assets) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans('alerts.reminder.subject', ['count' => $this->assets->count()]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.checkin-reminder', with: [
            'name' => $this->user->firstname ?: ($this->user->name ?? ''),
            'assets' => $this->assets,
        ]);
    }
}
