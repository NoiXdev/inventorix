<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\Evaluation\GuaranteeStatusReport;
use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarrantyStatsWidget extends BaseWidget
{
    protected static ?int $sort = -90;

    /** @return array{expired: int, soon_30: int, soon_90: int} */
    public function counts(): array
    {
        $today = now()->startOfDay();

        return [
            'expired' => Asset::query()
                ->whereNotNull('guarantee_end')
                ->whereDate('guarantee_end', '<', $today)
                ->count(),
            'soon_30' => Asset::query()
                ->whereNotNull('guarantee_end')
                ->whereDate('guarantee_end', '>=', $today)
                ->whereDate('guarantee_end', '<=', $today->copy()->addDays(30))
                ->count(),
            'soon_90' => Asset::query()
                ->whereNotNull('guarantee_end')
                ->whereDate('guarantee_end', '>=', $today)
                ->whereDate('guarantee_end', '<=', $today->copy()->addDays(90))
                ->count(),
        ];
    }

    protected function getStats(): array
    {
        $counts = $this->counts();
        $reportUrl = GuaranteeStatusReport::getUrl();

        return [
            Stat::make(trans('warranty.widget.stats.expired'), $counts['expired'])
                ->color('danger')
                ->url($reportUrl),
            Stat::make(trans('warranty.widget.stats.soon_30'), $counts['soon_30'])
                ->color('warning')
                ->url($reportUrl),
            Stat::make(trans('warranty.widget.stats.soon_90'), $counts['soon_90'])
                ->color('info')
                ->url($reportUrl),
        ];
    }
}
