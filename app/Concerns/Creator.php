<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stamps created_by / updated_by / deleted_by with the acting user.
 *
 * Pair with the `creator()` Blueprint macro, which adds the matching columns.
 * `deleted_by` only applies to models that also use SoftDeletes.
 */
trait Creator
{
    /**
     * Named bootCreator(), not boot(): Laravel calls boot<TraitName>() for every
     * trait automatically. A plain boot() here would be silently overridden by a
     * model that defines its own, and would fatally collide with any second trait
     * doing the same.
     */
    protected static function bootCreator(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->isDirty('created_by')) {
                $model->created_by = auth()->id();
            }

            if (! $model->isDirty('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (Model $model): void {
            // Only soft deletes keep a row to stamp; a hard delete is about to
            // remove it, so writing deleted_by would be a wasted UPDATE.
            if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($model->isForceDeleting()) {
                return;
            }

            // Eloquent's runSoftDelete() builds its UPDATE from a fixed column
            // list (deleted_at, updated_at), so an attribute merely assigned here
            // never reaches the database — it has to be saved explicitly.
            // Quietly, to avoid re-entering the updating hook above.
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
