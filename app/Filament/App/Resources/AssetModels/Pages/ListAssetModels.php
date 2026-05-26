<?php

namespace App\Filament\App\Resources\AssetModels\Pages;

use App\Filament\App\Resources\AssetModels\AssetModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetModels extends ListRecords
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
