<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssetsPerEmployeeReport extends AbstractReport
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

    public static function icon(): string
    {
        return 'Users';
    }

    public function filterConfig(): array
    {
        return [[
            'key' => 'employees', 'type' => 'multiselect',
            'label' => __('evaluation.reports.assets_per_employee.filter.employees'),
            'options' => User::query()->orderBy('name')->get()
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name ?? $u->email ?? '—'])->all(),
        ]];
    }

    public function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['assignedUser', 'model.category', 'status'])
            ->when(
                ! empty($this->filters['employees']),
                fn (Builder $query): Builder => $query->where('assignee_type', User::class)->whereIn('assignee_id', $this->filters['employees']),
            );
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.assets_per_employee.columns';

        return [
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->assignedUser?->name),
            ReportColumn::make('category', __("{$t}.category"), fn (Asset $a): ?string => $a->model?->category?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial', __("{$t}.serial"), fn (Asset $a): ?string => $a->serial),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->status?->name),
            ReportColumn::make('warranty_end', __("{$t}.warranty_end"), fn (Asset $a): ?string => $a->warrantyEnd()?->format('d.m.Y')),
            ReportColumn::make('purchase_cost', __("{$t}.purchase_cost"), fn (Asset $a) => $a->purchase_cost),
        ];
    }

    public function filterSummary(): string
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
     * Assets not assigned to a user are collected into a trailing "no owner" group.
     *
     * @return array<string, mixed>
     */
    public function pdfData(): array
    {
        $columns = $this->reportColumns();
        $noOwnerLabel = __('evaluation.reports.assets_per_employee.pdf.no_owner');

        $groups = $this->reportQuery()->get()
            ->groupBy(fn (Asset $asset): string => $asset->assignedUserId() ?? '')
            ->map(fn ($assets): array => [
                'employee' => $assets->first()->assignedUser?->name ?? $noOwnerLabel,
                'hasOwner' => $assets->first()->assignedUser !== null,
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
