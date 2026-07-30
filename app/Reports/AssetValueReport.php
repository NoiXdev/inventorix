<?php

namespace App\Reports;

use App\Models\Asset;
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
                    ['value' => 'asset_type', 'label' => __("{$g}.asset_type")],
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
            return Asset::query()->with(['owner', 'model', 'assetType']);
        }

        $query = Asset::query()
            ->selectRaw('count(*) as assets_count')
            ->selectRaw('coalesce(sum(buy_price), 0) as total_price');

        return match ($this->groupBy()) {
            'asset_type' => $query
                ->selectRaw('asset_type_id as id, asset_type_id')
                ->with('assetType')
                ->groupBy('asset_type_id'),
            'state' => $query
                ->selectRaw('state as id, state')
                ->groupBy('state'),
            default => $query
                ->selectRaw('owner_id as id, owner_id')
                ->with('owner')
                ->groupBy('owner_id'),
        };
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.asset_value.columns';

        if ($this->isDetailed()) {
            return [
                ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
                ReportColumn::make('asset_type', __("{$t}.asset_type"), fn (Asset $a): ?string => $a->assetType?->name),
                ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
                ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
                ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
                ReportColumn::make('buy_price', __("{$t}.buy_price"), fn (Asset $a) => $a->buy_price),
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
            'asset_type' => $row->assetType?->name ?? '—',
            'state' => $row->state?->getLabel() ?? '—',
            default => $row->owner?->name ?? __('evaluation.reports.assets_per_employee.pdf.no_owner'),
        };
    }

    public function isPaginated(): bool
    {
        return (bool) ($this->filters['detailed'] ?? false);
    }

    public function tableSummaries(): array
    {
        // Real DB column only summarizes meaningfully in detailed (per-asset) mode.
        return $this->isDetailed() ? ['buy_price'] : [];
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
