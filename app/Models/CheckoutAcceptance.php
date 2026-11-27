<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user is asked to accept (or decline) an item checked out to them. */
class CheckoutAcceptance extends Model
{
    use Creator, HasUuids;

    /** item_type => model */
    public const ITEM_TYPES = [
        'asset' => Asset::class,
        'accessory' => Accessory::class,
        'license' => License::class,
        'consumable' => Consumable::class,
    ];

    protected $fillable = ['user_id', 'item_type', 'item_id', 'checkout_id', 'item_name', 'eula_text', 'sent_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'reminded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->declined_at === null;
    }

    public function status(): string
    {
        return $this->accepted_at ? 'accepted' : ($this->declined_at ? 'declined' : 'pending');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('declined_at');
    }

    /** The item's page in the app, if it still exists. */
    public function itemUrl(): ?string
    {
        $class = self::ITEM_TYPES[$this->item_type] ?? null;
        if ($class === null || ! $class::query()->whereKey($this->item_id)->exists()) {
            return null;
        }

        return match ($this->item_type) {
            'asset' => "/app/assets/{$this->item_id}",
            'accessory' => "/app/accessories/{$this->item_id}",
            'license' => "/app/licenses/{$this->item_id}",
            'consumable' => "/app/consumables/{$this->item_id}",
        };
    }
}
