<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Models\Asset;
use App\Models\Place;
use App\Reports\ReportColumn;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class InventoryByLocationReport extends BaseReportPage
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

    public static function reportIcon(): string
    {
        return 'heroicon-o-map-pin';
    }

    protected function filterSchema(): array
    {
        return [
            Select::make('places')
                ->label(__('evaluation.reports.inventory_by_location.filter.places'))
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->options(fn (): array => Place::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    protected function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['place', 'owner', 'model', 'assetType'])
            ->when(
                ! empty($this->filters['places']),
                fn (Builder $query): Builder => $query->whereIn('place_id', $this->filters['places']),
            );
    }

    protected function reportColumns(): array
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
