<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\AssetModel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsWidget extends BaseWidget
{
    protected static ?int $sort = -100;

    protected function getStats(): array
    {
        $assets = Asset::all()->count();
        $licences = 0;

        return [
            Stat::make('Assets', $assets),
            Stat::make('Lizenzen', $licences),
            Stat::make('Total', $assets + $licences),
        ];
    }
}
