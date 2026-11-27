<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a user an item they had was checked back in. */
class ItemCheckedIn extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $itemName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans('acceptance.mail.checkin_subject', ['item' => $this->itemName]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.item-checked-in', with: ['name' => $this->user->firstname ?: ($this->user->name ?? '')]);
    }
}
