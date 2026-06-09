<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Enums\HandoverType;
use App\Models\Handover;
use App\Reports\ReportColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class HandoverHistoryReport extends BaseReportPage
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

    public static function reportIcon(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    protected function filterSchema(): array
    {
        return [
            Select::make('type')
                ->label(__('evaluation.reports.handover_history.filter.type'))
                ->multiple()
                ->live()
                ->options(HandoverType::class),
            DatePicker::make('from')
                ->label(__('evaluation.reports.handover_history.filter.from'))
                ->live(),
            DatePicker::make('to')
                ->label(__('evaluation.reports.handover_history.filter.to'))
                ->live(),
        ];
    }

    protected function reportQuery(): Builder
    {
        return Handover::query()
            ->with(['recipientUser', 'createdBy'])
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

    protected function reportColumns(): array
    {
        $t = 'evaluation.reports.handover_history.columns';

        return [
            ReportColumn::make('signed_at', __("{$t}.signed_at"), fn (Handover $h): ?string => $h->signed_at?->format('d.m.Y H:i')),
            ReportColumn::make('type', __("{$t}.type"), fn (Handover $h): ?string => $h->type?->getLabel()),
            ReportColumn::make('recipient', __("{$t}.recipient"), fn (Handover $h): ?string => $h->recipientUser?->name ?? $h->recipient_name),
            ReportColumn::make('assets_count', __("{$t}.assets_count"), fn (Handover $h): int => (int) $h->assets_count),
            ReportColumn::make('created_by', __("{$t}.created_by"), fn (Handover $h): ?string => $h->createdBy?->name),
        ];
    }
}
