<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;

class InventoryByLocationReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'inventory_by_location';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.inventory_by_location.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.inventory_by_location.description');
    }

    public static function icon(): string
    {
        return 'MapPin';
    }

    public function filterConfig(): array
    {
        return [
            [
                'key' => 'places',
                'type' => 'multiselect',
                'label' => __('evaluation.reports.inventory_by_location.filter.places'),
                'options' => Place::query()->orderBy('name')->get()
                    ->map(fn (Place $p): array => ['value' => (string) $p->id, 'label' => $p->name])->all(),
            ],
        ];
    }

    public function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['place', 'owner', 'model', 'assetType'])
            ->when(
                ! empty($this->filters['places']),
                fn (Builder $query): Builder => $query->whereIn('place_id', $this->filters['places']),
            );
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.inventory_by_location.columns';

        return [
            ReportColumn::make('place', __("{$t}.place"), fn (Asset $a): ?string => $a->place?->name),
            ReportColumn::make('asset_type', __("{$t}.asset_type"), fn (Asset $a): ?string => $a->assetType?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
        ];
    }
}
