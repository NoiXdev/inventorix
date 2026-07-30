<?php

// app/Support/Assets/AssetHistory.php

namespace App\Support\Assets;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Person;
use App\Models\Place;
use Spatie\Activitylog\Models\Activity;

class AssetHistory
{
    /** @var array<string, string> */
    private const LABELS = [
        'state' => 'State',
        'asset_type_id' => 'Asset type',
        'model_id' => 'Model',
        'owner_id' => 'Owner',
        'place_id' => 'Place',
        'serial_number' => 'Serial number',
        'buy_date' => 'Buy date',
        'buy_type' => 'Buy type',
        'buy_price' => 'Buy price',
        'guarantee_end' => 'Guarantee end',
        'invoice' => 'Invoice',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function for(Asset $asset): array
    {
        return Activity::query()
            ->where('subject_type', $asset->getMorphClass())
            ->where('subject_id', $asset->id)
            ->whereIn('event', ['created', 'updated', 'deleted'])
            ->with('causer')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Activity $a) => self::entry($a))
            ->all();
    }

    /** @return array<string, mixed> */
    private static function entry(Activity $a): array
    {
        $new = (array) data_get($a->attribute_changes, 'attributes', []);
        $old = (array) data_get($a->attribute_changes, 'old', []);

        $changes = [];
        foreach ($new as $field => $value) {
            if (! array_key_exists($field, self::LABELS)) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => self::LABELS[$field],
                'old' => self::resolve($field, $old[$field] ?? null),
                'new' => self::resolve($field, $value),
            ];
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

        return $causer === null ? 'Former user' : ($causer->person?->name ?? $causer->email ?? 'Former user');
    }

    private static function resolve(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'state' => AssetState::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'buy_type' => BuyType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'asset_type_id' => AssetType::find($value)?->name ?? '(removed)',
            'model_id' => AssetModel::find($value)?->name ?? '(removed)',
            'owner_id' => Person::find($value)?->name ?? '(removed)',
            'place_id' => Place::find($value)?->name ?? '(removed)',
            default => (string) $value,
        };
    }
}
