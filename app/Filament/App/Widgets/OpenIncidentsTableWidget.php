<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Assets\AssetResource;
use App\Models\Incident;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenIncidentsTableWidget extends BaseWidget
{
    protected static ?int $sort = -50;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('incident.widget.table.heading'))
            ->query(
                Incident::query()
                    ->whereNull('closed_date')
                    ->with('asset.model')
                    ->orderByDesc('open_date')
            )
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('title')
                    ->label(trans('incident.widget.table.title')),
                TextColumn::make('asset.model.name')
                    ->label(trans('incident.widget.table.model')),
                TextColumn::make('asset.serial_number')
                    ->label(trans('incident.widget.table.serial')),
                TextColumn::make('open_date')
                    ->label(trans('incident.widget.table.open_date'))
                    ->date('d.m.Y'),
                TextColumn::make('days_open')
                    ->label(trans('incident.widget.table.days_open'))
                    ->badge()
                    ->color('warning')
                    ->state(fn (Incident $record): int => (int) round($record->open_date->copy()->startOfDay()->diffInDays(now()->startOfDay(), false))),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(trans('incident.widget.table.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Incident $record): string => AssetResource::getUrl('edit', ['record' => $record->asset_id]))
                    ->visible(fn (Incident $record): bool => $record->asset_id !== null),
            ]);
    }
}
