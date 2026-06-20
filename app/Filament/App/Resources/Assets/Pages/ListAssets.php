<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Assets\Exporters\AssetExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(AssetExporter::class)
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['assetType', 'model.manufacturer', 'owner', 'place', 'tags'])),
        ];
    }
}
