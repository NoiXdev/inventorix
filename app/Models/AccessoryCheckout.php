<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One unit of an accessory checked out to a user, a location or an asset. */
class AccessoryCheckout extends Model
{
    use Creator, HasUuids;

    /** What an accessory can be checked out to: form key => morph class. */
    public const TARGET_TYPES = ['user' => User::class, 'location' => Location::class, 'asset' => Asset::class];

    protected $fillable = ['accessory_id', 'note'];

    protected function casts(): array
    {
        return ['checked_out_at' => 'datetime'];
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class)->withTrashed();
    }

    public function assigned(): MorphTo
    {
        return $this->morphTo('assigned', 'assigned_type', 'assigned_id');
    }

    /** The form key of the holder's kind: user, location or asset. */
    public function targetType(): ?string
    {
        return array_search($this->assigned_type, self::TARGET_TYPES, true) ?: null;
    }

    public function holderName(): ?string
    {
        $holder = $this->assigned;

        return match (true) {
            $holder instanceof User => $holder->name ?? $holder->email,
            $holder instanceof Location => $holder->name,
            $holder instanceof Asset => $holder->displayName(),
            default => null,
        };
    }

    /**
     * The accessories a user, location or asset holds, one row per checkout.
     *
     * @return list<array{checkout_id: string, accessory_id: string, name: string, checked_out_at: ?string}>
     */
    public static function heldBy(User|Location|Asset $holder): array
    {
        return self::query()
            ->where('assigned_type', $holder::class)->where('assigned_id', $holder->id)
            ->whereHas('accessory')
            ->with('accessory')
            ->orderBy('checked_out_at')
            ->get()
            ->map(fn (self $c) => [
                'checkout_id' => $c->id,
                'accessory_id' => $c->accessory_id,
                'name' => (string) $c->accessory->name,
                'checked_out_at' => $c->checked_out_at?->format('Y-m-d'),
            ])->all();
    }
}
