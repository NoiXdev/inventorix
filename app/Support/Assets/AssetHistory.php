<?php

// app/Support/Assets/AssetHistory.php

namespace App\Support\Assets;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AssetHistory
{
    /** @var array<string, string> */
    private const LABELS = [
        'name' => 'Name',
        'asset_tag' => 'Asset tag',
        'status_id' => 'Status',
        'state' => 'Status', // entries from before status labels replaced the fixed states
        'asset_type_id' => 'Category', // entries from before assets took their category from the model
        'model_id' => 'Model',
        'assignee_id' => 'Assigned to',
        'owner_id' => 'Assigned to', // entries from before the owner became the assignee
        'location_id' => 'Location',
        'place_id' => 'Location', // entries from before places became locations
        'rtd_location_id' => 'Default location',
        'supplier_id' => 'Supplier',
        'serial' => 'Serial',
        'serial_number' => 'Serial', // entries from before the renames
        'purchase_date' => 'Purchase date',
        'buy_date' => 'Purchase date',
        'purchase_cost' => 'Purchase cost',
        'buy_price' => 'Purchase cost',
        'order_number' => 'Order number',
        'warranty_months' => 'Warranty (months)',
        'asset_eol_date' => 'EOL date',
        'eol_explicit' => 'EOL date set by hand',
        'depreciate' => 'Depreciate',
        'physical' => 'Physical',
        'requestable' => 'Requestable',
        'byod' => 'BYOD',
        'archived' => 'Archived',
        'notes' => 'Notes',
        'expected_checkin' => 'Expected check-in',
        'last_audit_date' => 'Last audit',
        'next_audit_date' => 'Next audit',
        // Fields that were dropped; older entries still show them.
        'buy_type' => 'Buy type',
        'guarantee_end' => 'Guarantee end',
        'invoice' => 'Invoice',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function for(Asset $asset): array
    {
        // db_column => field, trashed fields included so older entries keep
        // their label.
        $custom = CustomField::withTrashed()->whereNotNull('db_column')->get()->keyBy('db_column');

        return Activity::query()
            ->where('subject_type', $asset->getMorphClass())
            ->where('subject_id', $asset->id)
            ->whereIn('event', ['created', 'updated', 'deleted'])
            ->with('causer')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Activity $a) => self::entry($a, $custom))
            ->all();
    }

    /**
     * @param  Collection<string, CustomField>  $custom
     * @return array<string, mixed>
     */
    private static function entry(Activity $a, Collection $custom): array
    {
        $new = (array) data_get($a->attribute_changes, 'attributes', []);
        $old = (array) data_get($a->attribute_changes, 'old', []);

        $changes = [];
        foreach ($new as $field => $value) {
            if (array_key_exists($field, self::LABELS)) {
                $changes[] = [
                    'field' => $field,
                    'label' => self::LABELS[$field],
                    'old' => self::resolve($field, $old[$field] ?? null),
                    'new' => self::resolve($field, $value),
                ];
            } elseif ($customField = $custom->get($field)) {
                $changes[] = [
                    'field' => $field,
                    'label' => $customField->name,
                    'old' => self::resolveCustom($customField, $old[$field] ?? null),
                    'new' => self::resolveCustom($customField, $value),
                ];
            }
        }

        return [
            'id' => $a->id,
            'event' => $a->event,
            'event_label' => $a->event ? ucfirst($a->event) : ($a->description ?: '—'),
            'causer_name' => self::causer($a),
            'created_at' => optional($a->created_at)->toDateTimeString(),
            'changes' => $changes,
        ];
    }

    private static function causer(Activity $a): string
    {
        if ($a->causer_id === null) {
            return 'System';
        }

        $causer = $a->causer;

        return $causer === null ? 'Former user' : ($causer->name ?? $causer->email ?? 'Former user');
    }

    /** Encrypted values are only marked as changed, never shown. */
    private static function resolveCustom(CustomField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $field->field_encrypted ? '(encrypted)' : (string) $value;
    }

    private static function resolve(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'status_id' => StatusLabel::withTrashed()->find($value)?->name ?? '(removed)',
            'state' => StatusLabel::withTrashed()->where('system_key', $value)->value('name') ?? (string) $value,
            'buy_type' => match ((string) $value) {
                'once' => 'Einmalig (gekauft)',
                'abo' => 'Abo',
                default => (string) $value,
            },
            'assignee_id' => Asset::assigneeNameFor(null, (string) $value) ?? '(removed)',
            'supplier_id' => Supplier::withTrashed()->find($value)?->name ?? '(removed)',
            'rtd_location_id' => Location::withTrashed()->find($value)?->name ?? '(removed)',
            'eol_explicit', 'depreciate', 'physical', 'requestable', 'byod', 'archived' => $value ? 'Yes' : 'No',
            'asset_type_id' => Category::withTrashed()->find($value)?->name ?? '(removed)',
            'model_id' => AssetModel::withTrashed()->find($value)?->name ?? '(removed)',
            'owner_id' => User::find($value)?->name ?? '(removed)',
            'location_id', 'place_id' => Location::withTrashed()->find($value)?->name ?? '(removed)',
            default => (string) $value,
        };
    }
}
