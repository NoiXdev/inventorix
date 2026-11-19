<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\Location;
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
                'key' => 'locations',
                'type' => 'multiselect',
                'label' => __('evaluation.reports.inventory_by_location.filter.locations'),
                'options' => Location::options(),
            ],
        ];
    }

    public function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['location', 'owner', 'model', 'assetType'])
            ->when(
                ! empty($this->filters['locations']),
                fn (Builder $query): Builder => $query->whereIn('location_id', $this->filters['locations']),
            );
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.inventory_by_location.columns';

        return [
            ReportColumn::make('location', __("{$t}.location"), fn (Asset $a): ?string => $a->location?->name),
            ReportColumn::make('asset_type', __("{$t}.asset_type"), fn (Asset $a): ?string => $a->assetType?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
        ];
    }
}
