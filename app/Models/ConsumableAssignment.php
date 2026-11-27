<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Units of a consumable handed to a user; the user's name is kept in case the user is deleted. */
class ConsumableAssignment extends Model
{
    use Creator, HasUuids;

    protected $fillable = ['consumable_id', 'user_id', 'user_name', 'qty', 'note', 'checked_out_at'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'checked_out_at' => 'datetime'];
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What a user was handed, newest first.
     *
     * @return list<array{id: string, consumable_id: string, name: string, qty: int, checked_out_at: ?string, deleted: bool}>
     */
    public static function handedTo(User $user): array
    {
        return self::query()->where('user_id', $user->id)
            ->whereHas('consumable')->with('consumable')
            ->latest('checked_out_at')->get()
            ->map(fn (self $a) => [
                'id' => $a->id,
                'consumable_id' => $a->consumable_id,
                'name' => $a->consumable->name,
                'qty' => $a->qty,
                'checked_out_at' => $a->checked_out_at?->format('Y-m-d'),
                // A deleted consumable has no page to link to.
                'deleted' => $a->consumable->trashed(),
            ])->all();
    }
}
