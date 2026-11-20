<?php

namespace App\Reports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;

class StateOverviewReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'state_overview';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.state_overview.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.state_overview.description');
    }

    public static function icon(): string
    {
        return 'PieChart';
    }

    public function filterConfig(): array
    {
        return [];
    }

    public function reportQuery(): Builder
    {
        return Asset::query()
            ->selectRaw('status_id as id, status_id')
            ->with('status')
            ->selectRaw('count(*) as assets_count')
            ->selectRaw('coalesce(sum(purchase_cost), 0) as total_price')
            ->groupBy('status_id');
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.state_overview.columns';

        return [
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->status?->name),
            ReportColumn::make('assets_count', __("{$t}.assets_count"), fn (Asset $a): int => (int) $a->assets_count),
            ReportColumn::make('total_price', __("{$t}.total_price"), fn (Asset $a) => $a->total_price),
        ];
    }

    public function isPaginated(): bool
    {
        return false;
    }
}
