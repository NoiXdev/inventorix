<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssetValueReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'asset_value';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.asset_value.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.asset_value.description');
    }

    public static function icon(): string
    {
        return 'Banknote';
    }

    public function defaultFilters(): array
    {
        return ['group_by' => 'employee', 'detailed' => false];
    }

    public function filterConfig(): array
    {
        $g = 'evaluation.reports.asset_value.group_by';

        return [
            [
                'key' => 'group_by', 'type' => 'select', 'nullable' => false, 'default' => 'employee',
                'label' => __('evaluation.reports.asset_value.filter.group_by'),
                'options' => [
                    ['value' => 'employee', 'label' => __("{$g}.employee")],
                    ['value' => 'category', 'label' => __("{$g}.category")],
                    ['value' => 'state', 'label' => __("{$g}.state")],
                ],
            ],
            [
                'key' => 'detailed', 'type' => 'toggle', 'default' => false,
                'label' => __('evaluation.reports.asset_value.filter.detailed'),
            ],
        ];
    }

    private function isDetailed(): bool
    {
        return (bool) ($this->filters['detailed'] ?? false);
    }

    private function groupBy(): string
    {
        return $this->filters['group_by'] ?? 'employee';
    }

    public function reportQuery(): Builder
    {
        if ($this->isDetailed()) {
            return Asset::query()->with(['assignedUser', 'model.category', 'status']);
        }

        $query = Asset::query()
            ->selectRaw('count(*) as assets_count')
            ->selectRaw('coalesce(sum(purchase_cost), 0) as total_price');

        return match ($this->groupBy()) {
            // The category is the model's; assets without one form their own group.
            'category' => $query
                ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
                ->leftJoin('categories', 'categories.id', '=', 'asset_models.category_id')
                ->selectRaw('asset_models.category_id as id, max(categories.name) as category_name')
                ->groupBy('asset_models.category_id'),
            'state' => $query
                ->selectRaw('status_id as id, status_id')
                ->with('status')
                ->groupBy('status_id'),
            // Per assigned user; assets assigned to anything else count as
            // unassigned. The user id is computed in a subquery so the outer
            // query groups by a plain column (MariaDB's ONLY_FULL_GROUP_BY).
            default => Asset::query()
                ->withoutGlobalScopes()
                ->fromSub(
                    Asset::query()->selectRaw('case when assignee_type = ? then assignee_id end as assigned_user_id, purchase_cost', [User::class]),
                    'assets',
                )
                ->selectRaw('count(*) as assets_count')
                ->selectRaw('coalesce(sum(purchase_cost), 0) as total_price')
                ->selectRaw('assigned_user_id as id, assigned_user_id as assignee_id')
                ->with('assignedUser')
                ->groupBy('assigned_user_id'),
        };
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.asset_value.columns';

        if ($this->isDetailed()) {
            return [
                ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->assignedUser?->name),
                ReportColumn::make('category', __("{$t}.category"), fn (Asset $a): ?string => $a->model?->category?->name),
                ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
                ReportColumn::make('serial', __("{$t}.serial"), fn (Asset $a): ?string => $a->serial),
                ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->status?->name),
                ReportColumn::make('purchase_cost', __("{$t}.purchase_cost"), fn (Asset $a) => $a->purchase_cost),
            ];
        }

        return [
            ReportColumn::make('group', __("{$t}.group"), fn (Asset $a): string => $this->groupLabel($a)),
            ReportColumn::make('assets_count', __("{$t}.assets_count"), fn (Asset $a): int => (int) $a->assets_count),
            ReportColumn::make('total_price', __("{$t}.total_price"), fn (Asset $a) => $a->total_price),
        ];
    }

    private function groupLabel(Asset $row): string
    {
        return match ($this->groupBy()) {
            'category' => $row->category_name ?? '—',
            'state' => $row->status?->name ?? '—',
            default => $row->assignedUser?->name ?? __('evaluation.reports.assets_per_employee.pdf.no_owner'),
        };
    }

    public function isPaginated(): bool
    {
        return (bool) ($this->filters['detailed'] ?? false);
    }

    public function tableSummaries(): array
    {
        // Real DB column only summarizes meaningfully in detailed (per-asset) mode.
        return $this->isDetailed() ? ['purchase_cost'] : [];
    }

    public function pdfData(): array
    {
        $data = parent::pdfData();
        // Append a grand-total row so the financial PDF always shows the sum.
        $priceIndex = $this->isDetailed() ? 5 : 2;
        $total = array_sum(array_map(
            fn (array $row): float => (float) ($row[$priceIndex] ?? 0),
            $data['rows'],
        ));

        $totalRow = array_fill(0, count($data['headings']), '');
        $totalRow[0] = __('evaluation.reports.asset_value.total');
        $totalRow[$priceIndex] = $total;
        $data['rows'][] = $totalRow;

        return $data;
    }
}
