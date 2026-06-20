<?php

namespace App\Filament\App\Resources\Assets\Exporters;

use App\Models\Asset;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AssetExporter extends Exporter
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('state')
                ->formatStateUsing(fn ($state): ?string => $state?->getLabel()),

            ExportColumn::make('asset_type')
                ->label('Asset Type')
                ->getStateUsing(fn (Asset $record): ?string => $record->assetType?->name),

            ExportColumn::make('manufacturer')
                ->getStateUsing(fn (Asset $record): ?string => $record->model?->manufacturer?->name),

            ExportColumn::make('model')
                ->getStateUsing(fn (Asset $record): ?string => $record->model?->name),

            ExportColumn::make('owner')
                ->getStateUsing(fn (Asset $record): ?string => $record->owner?->name),

            ExportColumn::make('place')
                ->getStateUsing(fn (Asset $record): ?string => $record->place?->name),

            ExportColumn::make('serial_number')
                ->label('Seriennummer'),

            ExportColumn::make('buy_date')
                ->label('Kaufdatum'),

            ExportColumn::make('guarantee_end')
                ->label('Garantie Ende'),

            ExportColumn::make('buy_price')
                ->label('Kaufpreis'),

            ExportColumn::make('buy_type')
                ->formatStateUsing(fn ($state): ?string => $state?->getLabel()),

            ExportColumn::make('tags')
                ->getStateUsing(fn (Asset $record): string => $record->tags->pluck('name')->implode(', ')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Der Asset-Export wurde abgeschlossen: '
            . number_format($export->successful_rows) . ' '
            . str('Zeile')->plural($export->successful_rows) . ' exportiert.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('Zeile')->plural($failedRowsCount) . ' fehlgeschlagen.';
        }

        return $body;
    }
}
