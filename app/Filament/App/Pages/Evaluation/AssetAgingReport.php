<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Models\Asset;
use App\Reports\ReportColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class AssetAgingReport extends BaseReportPage
{
    private const DEFAULT_MIN_AGE_YEARS = 3;

    public static function reportKey(): string
    {
        return 'asset_aging';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.asset_aging.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.asset_aging.description');
    }

    public static function reportIcon(): string
    {
        return 'heroicon-o-clock';
    }

    protected function filterSchema(): array
    {
        return [
            TextInput::make('min_age_years')
                ->label(__('evaluation.reports.asset_aging.filter.min_age_years'))
                ->numeric()
                ->minValue(0)
                ->default(self::DEFAULT_MIN_AGE_YEARS)
                ->live(debounce: 500),
        ];
    }

    private function minAgeYears(): int
    {
        $value = $this->filters['min_age_years'] ?? self::DEFAULT_MIN_AGE_YEARS;

        return max(0, (int) $value);
    }

    protected function reportQuery(): Builder
    {
        $cutoff = now()->startOfDay()->subYears($this->minAgeYears());

        return Asset::query()
            ->with(['owner', 'model'])
            ->whereNotNull('buy_date')
            ->whereDate('buy_date', '<=', $cutoff)
            ->orderBy('buy_date');
    }

    protected function reportColumns(): array
    {
        $t = 'evaluation.reports.asset_aging.columns';

        return [
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
            ReportColumn::make('buy_date', __("{$t}.buy_date"), fn (Asset $a): ?string => $a->buy_date?->format('d.m.Y')),
            ReportColumn::make('age_years', __("{$t}.age_years"), fn (Asset $a): ?int => $a->buy_date
                ? (int) floor($a->buy_date->copy()->startOfDay()->diffInYears(now()->startOfDay()))
                : null),
            ReportColumn::make('buy_price', __("{$t}.buy_price"), fn (Asset $a) => $a->buy_price),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
        ];
    }
}
