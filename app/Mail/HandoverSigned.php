<?php

namespace App\Mail;

use App\Models\Handover;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

class HandoverSigned extends Mailable
{

    public Handover $handover;

    public function __construct(public string $handoverId)
    {
        $this->handover = Handover::with(['assets.model', 'createdBy'])->findOrFail($handoverId);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('handover.mail.subject', ['type' => $this->handover->type->getLabel()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.handover-signed',
            with: [
                'handover' => $this->handover,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->handover->pdf_path) {
            return [];
        }

        $disk = config('handover.disk');
        $bytes = Storage::disk($disk)->get($this->handover->pdf_path);

        return [
            Attachment::fromData(fn () => $bytes, 'handover.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
