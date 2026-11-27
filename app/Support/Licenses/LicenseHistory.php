<?php

namespace App\Support\Licenses;

use App\Models\Category;
use App\Models\Depreciation;
use App\Models\License;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Spatie\Activitylog\Models\Activity;

/** The history of a license: changes and seat check-outs / check-ins, newest first. */
class LicenseHistory
{
    private const LABELS = [
        'name' => 'Name', 'category_id' => 'Category', 'manufacturer_id' => 'Manufacturer', 'supplier_id' => 'Supplier',
        'depreciation_id' => 'Depreciation', 'seats' => 'Seats', 'licensed_to_name' => 'Licensed to',
        'licensed_to_email' => 'Licensed to email', 'reassignable' => 'Reassignable', 'maintained' => 'Maintained',
        'requestable' => 'Requestable', 'purchase_date' => 'Purchase date', 'purchase_cost' => 'Purchase cost',
        'order_number' => 'Order number', 'purchase_order' => 'Purchase order', 'expiration_date' => 'Expiration date',
        'termination_date' => 'Termination date', 'min_amt' => 'Minimum free seats', 'notes' => 'Notes',
    ];

    private const EVENT_LABELS = ['checked_out' => 'Checked out', 'checked_in' => 'Checked in'];

    /** @return list<array<string, mixed>> */
    public static function for(License $license): array
    {
        return Activity::query()
            ->where('subject_type', $license->getMorphClass())
            ->where('subject_id', $license->id)
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

        return match ($field) {
            'category_id' => Category::withTrashed()->find($value)?->name ?? (string) $value,
            'manufacturer_id' => Manufacturer::withTrashed()->find($value)?->name ?? (string) $value,
            'supplier_id' => Supplier::withTrashed()->find($value)?->name ?? (string) $value,
            'depreciation_id' => Depreciation::withTrashed()->find($value)?->name ?? (string) $value,
            'purchase_date', 'expiration_date', 'termination_date' => substr((string) $value, 0, 10),
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }
}
