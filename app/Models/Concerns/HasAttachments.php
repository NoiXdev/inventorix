<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    protected static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            $model->attachments->each->delete(); // per-model delete → fires AttachmentObserver
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
