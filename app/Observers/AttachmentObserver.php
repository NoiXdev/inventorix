<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentObserver
{
    public function created(Attachment $attachment): void
    {
        $this->log($attachment, 'attachment_added');
    }

    public function deleted(Attachment $attachment): void
    {
        if ($attachment->path && Storage::disk()->exists($attachment->path)) {
            Storage::disk()->delete($attachment->path);
        }

        $this->log($attachment, 'attachment_removed');
    }

    private function log(Attachment $attachment, string $event): void
    {
        $subject = $attachment->attachable;

        if ($subject === null) {
            return;
        }

        activity('asset')
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties([
                'original_name' => $attachment->original_name,
                'title' => $attachment->title,
            ])
            ->log($event);
    }
}
