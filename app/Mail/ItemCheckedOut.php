<?php

namespace App\Mail;

use App\Models\User;
use App\Services\AcceptanceService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells a user an item was checked out to them, with the EULA and, if needed, the link to accept it. */
class ItemCheckedOut extends Mailable
{
    public function __construct(
        public User $user,
        public string $itemName,
        public ?string $eula,
        public ?string $acceptUrl,
        public bool $reminder = false,
    ) {}

    public function envelope(): Envelope
    {
        $key = $this->acceptUrl ? ($this->reminder ? 'acceptance.mail.reminder_subject' : 'acceptance.mail.accept_subject') : 'acceptance.mail.checkout_subject';

        return new Envelope(subject: trans($key, ['item' => $this->itemName]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.item-checked-out', with: [
            'name' => $this->user->firstname ?: ($this->user->name ?? ''),
            'linkDays' => AcceptanceService::LINK_DAYS,
        ]);
    }
}
