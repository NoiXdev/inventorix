<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GuaranteeStatusReport extends AbstractReport
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

    public static function icon(): string
    {
        return 'ShieldCheck';
    }

    public function filterConfig(): array
    {
        $s = 'evaluation.reports.guarantee_status.status';

        return [
            [
                'key' => 'status', 'type' => 'multiselect',
                'label' => __('evaluation.reports.guarantee_status.filter.status'),
                'options' => [
                    ['value' => 'expired', 'label' => __("{$s}.expired")],
                    ['value' => 'expiring_soon', 'label' => __("{$s}.expiring_soon")],
                    ['value' => 'valid', 'label' => __("{$s}.valid")],
                    ['value' => 'none', 'label' => __("{$s}.none")],
                ],
            ],
            [
                'key' => 'employees', 'type' => 'multiselect',
                'label' => __('evaluation.reports.guarantee_status.filter.employees'),
                'options' => User::query()->orderBy('name')->get()
                    ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name ?? $u->email ?? '—'])->all(),
            ],
        ];
    }

    public function reportQuery(): Builder
    {
        $today = now()->startOfDay();
        $soonLimit = now()->startOfDay()->addDays(self::EXPIRING_SOON_DAYS);

        return Asset::query()
            ->with(['assignee', 'model'])
            ->when(
                ! empty($this->filters['employees']),
                fn (Builder $query): Builder => $query->where('assignee_type', User::class)->whereIn('assignee_id', $this->filters['employees']),
            )
            ->when(
                ! empty($this->filters['status']),
                function (Builder $query) use ($today, $soonLimit): Builder {
                    $statuses = $this->filters['status'];

                    return $query->where(function (Builder $outer) use ($statuses, $today, $soonLimit): void {
                        foreach ($statuses as $status) {
                            $outer->orWhere(function (Builder $inner) use ($status, $today, $soonLimit): void {
                                $end = Asset::warrantyEndSql();
                                match ($status) {
                                    'expired' => $inner->withWarranty()->whereRaw("{$end} < ?", [$today->toDateString()]),
                                    'expiring_soon' => $inner->withWarranty()
                                        ->whereRaw("{$end} >= ?", [$today->toDateString()])
                                        ->whereRaw("{$end} <= ?", [$soonLimit->toDateString()]),
                                    'valid' => $inner->withWarranty()->whereRaw("{$end} > ?", [$soonLimit->toDateString()]),
                                    'none' => $inner->where(fn (Builder $q) => $q->whereNull('assets.purchase_date')->orWhereNull('assets.warranty_months')),
                                    default => $inner,
                                };
                            });
                        }
                    });
                },
            );
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.guarantee_status.columns';

        return [
            ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->assigneeName()),
            ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            ReportColumn::make('serial', __("{$t}.serial"), fn (Asset $a): ?string => $a->serial),
            ReportColumn::make('warranty_end', __("{$t}.warranty_end"), fn (Asset $a): ?string => $a->warrantyEnd()?->format('d.m.Y')),
            ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): string => $this->statusLabel($a)),
            ReportColumn::make('days_left', __("{$t}.days_left"), fn (Asset $a): ?int => $a->warrantyEnd()
                ? (int) round(now()->startOfDay()->diffInDays($a->warrantyEnd()->copy()->startOfDay(), false))
                : null),
        ];
    }

    private function statusLabel(Asset $asset): string
    {
        $s = 'evaluation.reports.guarantee_status.status';

        if ($asset->warrantyEnd() === null) {
            return __("{$s}.none");
        }

        $end = $asset->warrantyEnd()->copy()->startOfDay();
        $today = now()->startOfDay();

        return match (true) {
            $end->lt($today) => __("{$s}.expired"),
            $end->lte($today->copy()->addDays(self::EXPIRING_SOON_DAYS)) => __("{$s}.expiring_soon"),
            default => __("{$s}.valid"),
        };
    }
}
