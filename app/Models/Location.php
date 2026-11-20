<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

// image is set by the controller from an upload, never mass-assigned:
// accepting it as input would let a client point the row at any stored file.
#[Fillable(['name', 'address', 'address2', 'city', 'state', 'country', 'zip', 'phone', 'fax', 'parent_id', 'currency', 'ldap_ou', 'manager_id', 'notes'])]
class Location extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    /** Separator between the names in a location's path. */
    public const PATH_SEPARATOR = ' › ';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'location_id');
    }

    /**
     * Ids of every location below this one, at any depth. Trashed ones are
     * included: restoring one must not be able to close a cycle.
     *
     * @return list<string>
     */
    public function descendantIds(): array
    {
        $found = [];
        $level = [$this->getKey()];

        while ($level !== []) {
            $level = self::withTrashed()->whereIn('parent_id', $level)->whereNotIn('id', $found)->pluck('id')->all();
            array_push($found, ...$level);
        }

        return $found;
    }

    /**
     * Every location's full path ("HQ › Floor 2 › Room 201"), keyed by id,
     * built from one query.
     *
     * @return Collection<string, string>
     */
    public static function paths(bool $withTrashed = false): Collection
    {
        $all = ($withTrashed ? self::withTrashed() : self::query())->get(['id', 'name', 'parent_id'])->keyBy('id');

        return $all->map(function (Location $location) use ($all) {
            $names = [];
            $seen = [];
            for ($node = $location; $node && ! isset($seen[$node->id]); $node = $all->get($node->parent_id)) {
                $seen[$node->id] = true;
                array_unshift($names, $node->name ?? '—');
            }

            return implode(self::PATH_SEPARATOR, $names);
        });
    }

    /** @return list<array{value: string, label: string}> sorted by path */
    public static function options(?string $except = null): array
    {
        return self::paths()
            ->except($except ?? [])
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->map(fn (string $path, string $id) => ['value' => $id, 'label' => $path])
            ->values()
            ->all();
    }

    public function accessories(): HasMany
    {
        return $this->hasMany(Accessory::class, 'location_id');
    }
}
