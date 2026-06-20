<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Assets\Exporters\AssetExporter;
use App\Filament\App\Resources\Assets\Importers\AssetImporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()->importer(AssetImporter::class),
            ExportAction::make()
                ->exporter(AssetExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $query->with(['assetType', 'model.manufacturer', 'owner', 'place', 'tags'])),
        ];
    }
}
