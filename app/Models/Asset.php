<?php

namespace App\Models;

use App\Concerns\Creator;
use App\Models\Concerns\HasAttachments;
use App\Observers\AssetObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Tags\HasTags;

// image is set from an upload and the assignee through assignTo(); the
// checkout/checkin/request counters and dates are kept by the application.
#[Fillable([
    'name', 'asset_tag', 'status_id', 'model_id', 'serial', 'location_id', 'rtd_location_id', 'supplier_id',
    'purchase_date', 'purchase_cost', 'order_number', 'warranty_months', 'asset_eol_date', 'eol_explicit',
    'depreciate', 'physical', 'requestable', 'byod', 'archived', 'notes',
    'expected_checkin', 'last_audit_date', 'next_audit_date',
])]
#[ObservedBy(AssetObserver::class)]
class Asset extends Model
{
    use Creator, HasAttachments, HasFactory, HasTags, HasUuids, LogsActivity, SoftDeletes;

    /** What an asset can be assigned to: form/CSV key => morph class. */
    public const ASSIGNEE_TYPES = [
        'user' => User::class,
        'location' => Location::class,
        'asset' => self::class,
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
    }

    /** The user, location or asset this asset is assigned to. */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The assignee when it is a user. Ids are UUIDs, so a location or asset
     * id never matches a user; this lets queries eager-load and group by the
     * assigned user without going through the morph.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** Assets assigned to this asset (e.g. a dock and its monitors). */
    public function assignedAssets(): MorphMany
    {
        return $this->morphMany(self::class, 'assignee');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** The default ("ready to deploy") location the asset goes back to. */
    public function rtdLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'rtd_location_id')->withTrashed();
    }

    /** Assign to a user, location or asset, or unassign with null. */
    public function assignTo(?Model $assignee): static
    {
        $this->assignee_type = $assignee?->getMorphClass();
        $this->assignee_id = $assignee?->getKey();

        return $this;
    }

    /** The assigned user's id, null when unassigned or assigned to something else. */
    public function assignedUserId(): ?string
    {
        return $this->assignee_type === User::class ? $this->assignee_id : null;
    }

    /** The form/CSV key for the assignee type ('user', 'location', 'asset'). */
    public function assigneeKey(): ?string
    {
        $key = array_search($this->assignee_type, self::ASSIGNEE_TYPES, true);

        return $key === false ? null : $key;
    }

    /** A display name for the assignee, whatever kind it is. */
    public function assigneeName(): ?string
    {
        return self::nameOf($this->assignee);
    }

    /**
     * The display name of an assignee given by type and id, deleted records
     * included. Without a type, each assignee kind is tried (ids are UUIDs,
     * so they don't collide); used for history entries.
     */
    public static function assigneeNameFor(?string $type, ?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        foreach ($type ? [$type] : self::ASSIGNEE_TYPES as $class) {
            if (! in_array($class, self::ASSIGNEE_TYPES, true)) {
                continue;
            }
            $query = $class::query();
            if (method_exists($class, 'bootSoftDeletes')) {
                $query->withTrashed();
            }
            if ($found = $query->find($id)) {
                return self::nameOf($found);
            }
        }

        return null;
    }

    private static function nameOf(?Model $assignee): ?string
    {
        return match (true) {
            $assignee instanceof User => $assignee->name ?? $assignee->email,
            $assignee instanceof Location => (string) Location::paths(withTrashed: true)->get($assignee->id, $assignee->name),
            $assignee instanceof self => $assignee->displayName(),
            default => null,
        };
    }

    /** Name, else tag, else serial: what the UI calls an asset. */
    public function displayName(): string
    {
        return $this->name ?: ($this->asset_tag ?: ($this->serial ?: (string) $this->id));
    }

    /** Assets assigned to the given user. */
    public function scopeAssignedToUser(Builder $query, User|string $user): void
    {
        $query->where('assets.assignee_type', User::class)
            ->where('assets.assignee_id', $user instanceof User ? $user->id : $user);
    }

    /** End of warranty: purchase date plus the warranty months. */
    public function warrantyEnd(): ?CarbonInterface
    {
        if ($this->purchase_date === null || $this->warranty_months === null) {
            return null;
        }

        return $this->purchase_date->copy()->addMonthsNoOverflow($this->warranty_months);
    }

    /** Assets that have a warranty end (purchase date and months set). */
    public function scopeWithWarranty(Builder $query): void
    {
        $query->whereNotNull('assets.purchase_date')->whereNotNull('assets.warranty_months');
    }

    /**
     * The warranty end as an SQL expression, for filtering and sorting. Only
     * meaningful together with scopeWithWarranty().
     */
    public static function warrantyEndSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date(assets.purchase_date, '+' || assets.warranty_months || ' months')"
            : 'DATE_ADD(assets.purchase_date, INTERVAL assets.warranty_months MONTH)';
    }

    public function status(): BelongsTo
    {
        // Trashed labels included, so an asset still shows its status.
        return $this->belongsTo(StatusLabel::class, 'status_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        // Trashed locations included, so an asset still shows where it is.
        return $this->belongsTo(Location::class, 'location_id')->withTrashed();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'asset_id')
            ->orderBy('open_date', 'asc');
    }

    public function handovers(): BelongsToMany
    {
        return $this->belongsToMany(Handover::class, 'handover_asset')
            ->using(HandoverAsset::class)
            ->withPivot(['id', 'status_from_id', 'status_to_id', 'owner_from_id', 'owner_to_id'])
            ->withTimestamps()
            ->orderByDesc('handovers.signed_at');
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'asset_eol_date' => 'date',
            'eol_explicit' => 'boolean',
            'physical' => 'boolean',
            'archived' => 'boolean',
            'depreciate' => 'boolean',
            'requestable' => 'boolean',
            'byod' => 'boolean',
            'warranty_months' => 'integer',
            'last_checkout' => 'datetime',
            'last_checkin' => 'datetime',
            'expected_checkin' => 'datetime',
            'last_audit_date' => 'datetime',
            'next_audit_date' => 'date',
            'checkin_counter' => 'integer',
            'checkout_counter' => 'integer',
            'requests_counter' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'asset_tag',
                'model_id',
                'assignee_type',
                'assignee_id',
                'location_id',
                'rtd_location_id',
                'supplier_id',
                'serial',
                'purchase_date',
                'purchase_cost',
                'order_number',
                'warranty_months',
                'asset_eol_date',
                'eol_explicit',
                'depreciate',
                'physical',
                'requestable',
                'byod',
                'archived',
                'notes',
                'expected_checkin',
                'last_audit_date',
                'next_audit_date',
                'status_id',
                // Custom field columns, trashed fields included: their columns
                // still exist and can still change.
                ...CustomField::withTrashed()->pluck('db_column')->filter()->all(),
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('asset');
    }
}
