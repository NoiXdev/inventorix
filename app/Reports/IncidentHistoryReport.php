<?php

namespace App\Reports;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;

class IncidentHistoryReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'incident_history';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.incident_history.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.incident_history.description');
    }

    public static function icon(): string
    {
        return 'Wrench';
    }

    public function filterConfig(): array
    {
        $s = 'evaluation.reports.incident_history.status';

        return [
            ['key' => 'from', 'type' => 'date', 'label' => __('evaluation.reports.incident_history.filter.from')],
            ['key' => 'to', 'type' => 'date', 'label' => __('evaluation.reports.incident_history.filter.to')],
            [
                'key' => 'status', 'type' => 'select', 'nullable' => true,
                'label' => __('evaluation.reports.incident_history.filter.status'),
                'options' => [
                    ['value' => 'open', 'label' => __("{$s}.open")],
                    ['value' => 'closed', 'label' => __("{$s}.closed")],
                ],
            ],
        ];
    }

    public function reportQuery(): Builder
    {
        return Incident::query()
            ->with(['asset.owner', 'asset.model'])
            ->when(
                ! empty($this->filters['from']),
                fn (Builder $query): Builder => $query->whereDate('open_date', '>=', $this->filters['from']),
            )
            ->when(
                ! empty($this->filters['to']),
                fn (Builder $query): Builder => $query->whereDate('open_date', '<=', $this->filters['to']),
            )
            ->when(
                ($this->filters['status'] ?? null) === 'open',
                fn (Builder $query): Builder => $query->whereNull('closed_date'),
            )
            ->when(
                ($this->filters['status'] ?? null) === 'closed',
                fn (Builder $query): Builder => $query->whereNotNull('closed_date'),
            )
            ->orderByDesc('open_date');
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.incident_history.columns';
        $s = 'evaluation.reports.incident_history.status';

        return [
            ReportColumn::make('asset', __("{$t}.asset"), fn (Incident $i): ?string => $i->asset?->model?->name ?? $i->asset?->serial_number),
            ReportColumn::make('owner', __("{$t}.owner"), fn (Incident $i): ?string => $i->asset?->owner?->name),
            ReportColumn::make('title', __("{$t}.title"), fn (Incident $i): ?string => $i->title),
            ReportColumn::make('open_date', __("{$t}.open_date"), fn (Incident $i): ?string => $i->open_date?->format('d.m.Y')),
            ReportColumn::make('closed_date', __("{$t}.closed_date"), fn (Incident $i): ?string => $i->closed_date?->format('d.m.Y')),
            ReportColumn::make('state', __("{$t}.state"), fn (Incident $i): string => $i->closed_date ? __("{$s}.closed") : __("{$s}.open")),
        ];
    }
}
