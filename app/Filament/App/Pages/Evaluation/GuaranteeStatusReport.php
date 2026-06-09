<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Models\Asset;
use App\Models\User;
use App\Reports\ReportColumn;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class GuaranteeStatusReport extends BaseReportPage
{
    private const EXPIRING_SOON_DAYS = 90;

    public static function reportKey(): string
    {
        return 'guarantee_status';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.guarantee_status.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.guarantee_status.description');
    }

    public static function reportIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    protected function filterSchema(): array
    {
        $s = 'evaluation.reports.guarantee_status.status';

        return [
            Select::make('status')
                ->label(__('evaluation.reports.guarantee_status.filter.status'))
                ->multiple()
                ->live()
                ->options([
                    'expired' => __("{$s}.expired"),
                    'expiring_soon' => __("{$s}.expiring_soon"),
                    'valid' => __("{$s}.valid"),
                    'none' => __("{$s}.none"),
                ]),
            Select::make('employees')
                ->label(__('evaluation.reports.guarantee_status.filter.employees'))
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    protected function reportQuery(): Builder
    {
        $today = now()->startOfDay();
        $soonLimit = now()->startOfDay()->addDays(self::EXPIRING_SOON_DAYS);

        return Asset::query()
            ->with(['owner', 'model'])
            ->when(
                ! empty($this->filters['employees']),
                fn (Builder $query): Builder => $query->whereIn('owner_id', $this->filters['employees']),
            )
            ->when(
                ! empty($this->filters['status']),
                function (Builder $query) use ($today, $soonLimit): Builder {
                    $statuses = $this->filters['status'];

                    return $query->where(function (Builder $outer) use ($statuses, $today, $soonLimit): void {
                        foreach ($statuses as $status) {
                            $outer->orWhere(function (Builder $inner) use ($status, $today, $soonLimit): void {
                                match ($status) {
                                    'expired' => $inner->whereNotNull('guarantee_end')->whereDate('guarantee_end', '<', $today),
                                    'expiring_soon' => $inner->whereNotNull('guarantee_end')
                                        ->whereDate('guarantee_end', '>=', $today)
                                        ->whereDate('guarantee_end', '<=', $soonLimit),
                                    'valid' => $inner->whereNotNull('guarantee_end')->whereDate('guarantee_end', '>', $soonLimit),
                                    'none' => $inner->whereNull('guarantee_end'),
                                    default => $inner,
                                };
                            });
                        }
                    });
                },
            );
    }

    protected function reportColumns(): array
    {
        $t = 'evaluation.reports.guarantee_status.columns';

        return [
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            ReportColumn::make('guarantee_end', __("{$t}.guarantee_end"), fn (Asset $a): ?string => $a->guarantee_end?->format('d.m.Y')),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): string => $this->statusLabel($a)),
            ReportColumn::make('days_left', __("{$t}.days_left"), fn (Asset $a): ?int => $a->guarantee_end
                ? (int) round(now()->startOfDay()->diffInDays($a->guarantee_end->copy()->startOfDay(), false))
                : null),
        ];
    }

    private function statusLabel(Asset $asset): string
    {
        $s = 'evaluation.reports.guarantee_status.status';

        if ($asset->guarantee_end === null) {
            return __("{$s}.none");
        }

        $end = $asset->guarantee_end->copy()->startOfDay();
        $today = now()->startOfDay();

        return match (true) {
            $end->lt($today) => __("{$s}.expired"),
            $end->lte($today->copy()->addDays(self::EXPIRING_SOON_DAYS)) => __("{$s}.expiring_soon"),
            default => __("{$s}.valid"),
        };
    }
}
