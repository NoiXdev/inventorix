<?php

namespace App\Support\Consumables;

use App\Models\Category;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Spatie\Activitylog\Models\Activity;

/** The history of a consumable: changes and what was handed out, newest first. */
class ConsumableHistory
{
    private const LABELS = [
        'name' => 'Name', 'category_id' => 'Category', 'manufacturer_id' => 'Manufacturer', 'model_number' => 'Model number',
        'item_no' => 'Item number', 'location_id' => 'Location', 'supplier_id' => 'Supplier', 'qty' => 'Quantity',
        'min_amt' => 'Minimum quantity', 'purchase_date' => 'Purchase date', 'purchase_cost' => 'Purchase cost',
        'order_number' => 'Order number',
        'requestable' => 'Requestable', 'archived' => 'Archived', 'notes' => 'Notes',
    ];

    private const EVENT_LABELS = ['checked_out' => 'Checked out'];

    /** @var array<string, array<string, ?string>> field => id => name, per for() call */
    private static array $names = [];

    /** @return list<array<string, mixed>> */
    public static function for(Consumable $consumable): array
    {
        self::$names = [];

        return Activity::query()
            ->where('subject_type', $consumable->getMorphClass())
            ->where('subject_id', $consumable->id)
            ->with('causer')
            ->latest()->latest('id')
            ->get()
            ->map(function (Activity $a) {
                $new = (array) data_get($a->attribute_changes, 'attributes', []);
                $old = (array) data_get($a->attribute_changes, 'old', []);
                $changes = [];
                foreach ($new as $field => $value) {
                    if (isset(self::LABELS[$field])) {
                        $changes[] = [
                            'field' => $field, 'label' => self::LABELS[$field],
                            'old' => self::resolve($field, $old[$field] ?? null), 'new' => self::resolve($field, $value),
                        ];
                    }
                }
                $target = $a->getProperty('target');

                return [
                    'id' => $a->id,
                    'event' => $a->event,
                    'event_label' => self::EVENT_LABELS[$a->event] ?? ucfirst((string) $a->event),
                    'note' => trim(implode("\n", array_filter([$target, $a->getProperty('note')]))) ?: null,
                    'causer_name' => $a->causer_id === null ? 'System' : ($a->causer?->name ?? $a->causer?->email ?? 'Former user'),
                    'created_at' => optional($a->created_at)->toDateTimeString(),
                    'changes' => $changes,
                ];
            })->all();
    }

    private static function resolve(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (in_array($field, ['category_id', 'manufacturer_id', 'supplier_id', 'location_id'], true)) {
            // Each linked name is looked up once per page, not once per entry.
            return self::$names[$field][$value] ??= match ($field) {
                'category_id' => Category::withTrashed()->find($value)?->name,
                'manufacturer_id' => Manufacturer::withTrashed()->find($value)?->name,
                'supplier_id' => Supplier::withTrashed()->find($value)?->name,
                'location_id' => (self::$names['_paths'] ??= Location::paths(withTrashed: true)->all())[$value] ?? null,
            } ?? (string) $value;
        }

        if ($field === 'purchase_date') {
            return substr((string) $value, 0, 10);
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
