<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    protected static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            // A soft-deleted model can be restored, so its files stay until
            // it is deleted for good.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->attachments->each->delete(); // per-model delete → fires AttachmentObserver
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
