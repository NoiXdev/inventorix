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
            ->with(['location', 'assignee', 'model.category', 'status'])
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
            ReportColumn::make('category', __("{$t}.category"), fn (Asset $a): ?string => $a->model?->category?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial', __("{$t}.serial"), fn (Asset $a): ?string => $a->serial),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->status?->name),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->assigneeName()),
        ];
    }
}
