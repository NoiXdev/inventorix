<?php

namespace App\Filament\App\Pages\Evaluation;

class ReportRegistry
{
    /**
     * Implemented report pages, in display order.
     *
     * @return array<int, class-string<BaseReportPage>>
     */
    public static function all(): array
    {
        return [
            AssetsPerEmployeeReport::class,
            GuaranteeStatusReport::class,
            InventoryByLocationReport::class,
        ];
    }
}
