<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Models\Asset;
use App\Models\User;
use App\Reports\ReportColumn;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class AssetsPerEmployeeReport extends BaseReportPage
{
    public static function reportKey(): string
    {
        return 'assets_per_employee';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.assets_per_employee.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.assets_per_employee.description');
    }

    public static function reportIcon(): string
    {
        return 'heroicon-o-user-group';
    }

    protected function filterSchema(): array
    {
        return [
            Select::make('employees')
                ->label(__('evaluation.reports.assets_per_employee.filter.employees'))
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    protected function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['owner', 'model', 'assetType'])
            ->when(
                ! empty($this->filters['employees']),
                fn (Builder $query): Builder => $query->whereIn('owner_id', $this->filters['employees']),
            );
    }

    protected function reportColumns(): array
    {
        $t = 'evaluation.reports.assets_per_employee.columns';

        return [
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
            ReportColumn::make('asset_type', __("{$t}.asset_type"), fn (Asset $a): ?string => $a->assetType?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
            ReportColumn::make('guarantee_end', __("{$t}.guarantee_end"), fn (Asset $a): ?string => $a->guarantee_end?->format('d.m.Y')),
            ReportColumn::make('buy_price', __("{$t}.buy_price"), fn (Asset $a) => $a->buy_price),
        ];
    }

    protected function filterSummary(): string
    {
        if (empty($this->filters['employees'])) {
            return __('evaluation.reports.assets_per_employee.filter.employees').': —';
        }

        $names = User::query()->whereIn('id', $this->filters['employees'])->pluck('name')->implode(', ');

        return __('evaluation.reports.assets_per_employee.filter.employees').': '.$names;
    }

    public function pdfView(): string
    {
        return 'pdf.reports.assets-per-employee';
    }

    /**
     * Group the assets per employee so each employee starts on its own PDF page.
     * Assets without an owner are collected into a trailing "no owner" group.
     *
     * @return array<string, mixed>
     */
    public function pdfData(): array
    {
        $columns = $this->reportColumns();
        $noOwnerLabel = __('evaluation.reports.assets_per_employee.pdf.no_owner');

        $groups = $this->reportQuery()->get()
            ->groupBy(fn (Asset $asset): string => $asset->owner_id ?? '')
            ->map(fn ($assets): array => [
                'employee' => $assets->first()->owner?->name ?? $noOwnerLabel,
                'hasOwner' => $assets->first()->owner !== null,
                'rows' => $assets->map(fn (Asset $asset): array => array_map(
                    fn (ReportColumn $column) => $column->resolve($asset),
                    $columns,
                ))->all(),
            ])
            // Sort by employee name, but always push the "no owner" group to the end.
            ->sortBy(fn (array $group): string => ($group['hasOwner'] ? '0' : '1').$group['employee'])
            ->values()
            ->all();

        return [
            'title' => static::reportLabel(),
            'headings' => $this->reportHeadings(),
            'groups' => $groups,
            'filterSummary' => $this->filterSummary(),
            'companyName' => config('handover.company.name'),
            'generatedAt' => now()->format('d.m.Y H:i'),
        ];
    }
}
