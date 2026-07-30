<?php

namespace App\Reports;

use App\Enums\HandoverType;
use App\Models\Handover;
use Illuminate\Database\Eloquent\Builder;

class HandoverHistoryReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'handover_history';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.handover_history.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.handover_history.description');
    }

    public static function icon(): string
    {
        return 'ClipboardCheck';
    }

    public function filterConfig(): array
    {
        return [
            [
                'key' => 'type', 'type' => 'multiselect',
                'label' => __('evaluation.reports.handover_history.filter.type'),
                'options' => array_map(
                    fn (HandoverType $t): array => ['value' => $t->value, 'label' => $t->getLabel()],
                    HandoverType::cases(),
                ),
            ],
            ['key' => 'from', 'type' => 'date', 'label' => __('evaluation.reports.handover_history.filter.from')],
            ['key' => 'to', 'type' => 'date', 'label' => __('evaluation.reports.handover_history.filter.to')],
        ];
    }

    public function reportQuery(): Builder
    {
        return Handover::query()
            ->with(['recipientPerson', 'createdBy'])
            ->withCount('assets')
            ->when(
                ! empty($this->filters['type']),
                fn (Builder $query): Builder => $query->whereIn('type', $this->filters['type']),
            )
            ->when(
                ! empty($this->filters['from']),
                fn (Builder $query): Builder => $query->whereDate('signed_at', '>=', $this->filters['from']),
            )
            ->when(
                ! empty($this->filters['to']),
                fn (Builder $query): Builder => $query->whereDate('signed_at', '<=', $this->filters['to']),
            )
            ->orderByDesc('signed_at');
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.handover_history.columns';

        return [
            ReportColumn::make('signed_at', __("{$t}.signed_at"), fn (Handover $h): ?string => $h->signed_at?->format('d.m.Y H:i')),
            ReportColumn::make('type', __("{$t}.type"), fn (Handover $h): ?string => $h->type?->getLabel()),
            ReportColumn::make('recipient', __("{$t}.recipient"), fn (Handover $h): ?string => $h->recipientPerson?->name ?? $h->recipient_name),
            ReportColumn::make('assets_count', __("{$t}.assets_count"), fn (Handover $h): int => (int) $h->assets_count),
            ReportColumn::make('created_by', __("{$t}.created_by"), fn (Handover $h): ?string => $h->createdBy?->name),
        ];
    }
}
