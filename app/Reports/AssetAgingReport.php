<?php

namespace App\Reports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;

class AssetAgingReport extends AbstractReport
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

    public static function icon(): string
    {
        return 'Clock';
    }

    public function defaultFilters(): array
    {
        return ['min_age_years' => self::DEFAULT_MIN_AGE_YEARS];
    }

    public function filterConfig(): array
    {
        return [[
            'key' => 'min_age_years', 'type' => 'number', 'default' => self::DEFAULT_MIN_AGE_YEARS, 'min' => 0,
            'label' => __('evaluation.reports.asset_aging.filter.min_age_years'),
        ]];
    }

    private function minAgeYears(): int
    {
        $value = $this->filters['min_age_years'] ?? self::DEFAULT_MIN_AGE_YEARS;

        return max(0, (int) $value);
    }

    public function reportQuery(): Builder
    {
        $cutoff = now()->startOfDay()->subYears($this->minAgeYears());

        return Asset::query()
            ->with(['assignee', 'model', 'status'])
            ->whereNotNull('purchase_date')
            ->whereDate('purchase_date', '<=', $cutoff)
            ->orderBy('purchase_date');
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.asset_aging.columns';

        return [
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial', __("{$t}.serial"), fn (Asset $a): ?string => $a->serial),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->assigneeName()),
            ReportColumn::make('purchase_date', __("{$t}.purchase_date"), fn (Asset $a): ?string => $a->purchase_date?->format('d.m.Y')),
            ReportColumn::make('age_years', __("{$t}.age_years"), fn (Asset $a): ?int => $a->purchase_date
                ? (int) floor($a->purchase_date->copy()->startOfDay()->diffInYears(now()->startOfDay()))
                : null),
            ReportColumn::make('purchase_cost', __("{$t}.purchase_cost"), fn (Asset $a) => $a->purchase_cost),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->status?->name),
        ];
    }
}
