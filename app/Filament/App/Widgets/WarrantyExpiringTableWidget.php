<?php

namespace App\Filament\App\Widgets;

use App\Models\Asset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WarrantyExpiringTableWidget extends BaseWidget
{
    protected static ?int $sort = -80;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('warranty.widget.table.heading'))
            ->query(
                Asset::query()
                    ->whereNotNull('guarantee_end')
                    ->with(['owner', 'model'])
                    ->orderBy('guarantee_end')
            )
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('owner.name')
                    ->label(trans('warranty.widget.table.owner')),
                TextColumn::make('model.name')
                    ->label(trans('warranty.widget.table.model')),
                TextColumn::make('serial_number')
                    ->label(trans('warranty.widget.table.serial')),
                TextColumn::make('guarantee_end')
                    ->label(trans('warranty.widget.table.guarantee_end'))
                    ->date('d.m.Y')
                    ->badge()
                    ->color(fn (Asset $record): string => $record->guarantee_end->isPast() ? 'danger' : 'warning'),
                TextColumn::make('days_left')
                    ->label(trans('warranty.widget.table.days_left'))
                    ->state(fn (Asset $record): int => (int) round(now()->startOfDay()->diffInDays($record->guarantee_end->copy()->startOfDay(), false))),
            ]);
    }
}
