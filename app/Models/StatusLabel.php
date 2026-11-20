<?php

namespace App\Models;

use App\Concerns\Creator;
use App\Enums\StatusLabelType;
use App\Enums\SystemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

// system_key is fixed by the migration that seeded the system labels and is
// never mass-assigned.
#[Fillable(['name', 'deployable', 'pending', 'archived', 'notes', 'show_in_nav', 'default_label'])]
class StatusLabel extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'deployable' => 'boolean',
            'pending' => 'boolean',
            'archived' => 'boolean',
            'show_in_nav' => 'boolean',
            'default_label' => 'boolean',
            'system_key' => SystemStatus::class,
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'status_id');
    }

    /** The label the application uses for a system status, renamed or not. */
    public static function system(SystemStatus $status): self
    {
        return self::withTrashed()->where('system_key', $status->value)->firstOrFail();
    }

    /**
     * Ids of the labels for the given system statuses.
     *
     * @param  array<int, SystemStatus>  $statuses
     * @return list<string>
     */
    public static function systemIds(array $statuses): array
    {
        return self::withTrashed()
            ->whereIn('system_key', array_map(fn (SystemStatus $s) => $s->value, $statuses))
            ->pluck('id')->all();
    }

    /** The label new assets start with, if one is marked as default. */
    public static function default(): ?self
    {
        return self::query()->where('default_label', true)->first();
    }

    /**
     * Label names by id, trashed included, for resolving stored ids (history,
     * handovers) in one query.
     *
     * @return Collection<string, string|null>
     */
    public static function names(): Collection
    {
        return self::withTrashed()->pluck('name', 'id');
    }

    /** @return list<array{value: string, label: string, type: string}> */
    public static function options(): array
    {
        return self::query()->orderBy('name')->get()
            ->map(fn (self $l) => ['value' => $l->id, 'label' => (string) $l->name, 'type' => $l->type->value])
            ->all();
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    protected function type(): Attribute
    {
        return Attribute::get(fn () => StatusLabelType::fromFlags((bool) $this->deployable, (bool) $this->pending, (bool) $this->archived));
    }
}
