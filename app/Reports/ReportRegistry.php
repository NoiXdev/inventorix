<?php

namespace App\Reports;

class ReportRegistry
{
    /** @return array<int, class-string<AbstractReport>> */
    public static function all(): array
    {
        return [
            AssetsPerEmployeeReport::class,
            GuaranteeStatusReport::class,
            InventoryByLocationReport::class,
            AssetValueReport::class,
            StateOverviewReport::class,
            IncidentHistoryReport::class,
            AssetAgingReport::class,
            HandoverHistoryReport::class,
        ];
    }

    /** @return class-string<AbstractReport>|null */
    public static function find(string $key): ?string
    {
        foreach (static::all() as $class) {
            if ($class::reportKey() === $key) {
                return $class;
            }
        }

        return null;
    }
}
