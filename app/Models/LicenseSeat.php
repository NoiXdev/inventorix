<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One seat of a license, free or checked out to a user or an asset. */
class LicenseSeat extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['license_id', 'notes'];

    protected function casts(): array
    {
        return ['checked_out_at' => 'datetime', 'retired' => 'boolean'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'assigned_asset_id')->withTrashed();
    }

    public function isCheckedOut(): bool
    {
        return $this->assigned_user_id !== null || $this->assigned_asset_id !== null;
    }

    /**
     * The licenses a user or an asset holds seats of, for their pages.
     *
     * @return list<array{seat_id: string, license_id: string, name: string, checked_out_at: ?string}>
     */
    public static function heldBy(User|Asset $holder): array
    {
        return self::query()
            ->where($holder instanceof User ? 'assigned_user_id' : 'assigned_asset_id', $holder->id)
            ->whereHas('license')
            ->with('license')
            ->orderBy('checked_out_at')
            ->get()
            ->map(fn (self $s) => [
                'seat_id' => $s->id,
                'license_id' => $s->license_id,
                'name' => $s->license->name,
                'checked_out_at' => $s->checked_out_at?->format('Y-m-d'),
            ])->all();
    }

    /** Who or what has the seat, for lists. */
    public function holderName(): ?string
    {
        return $this->user?->name ?? $this->user?->email ?? $this->asset?->displayName();
    }
}
