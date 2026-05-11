<?php

namespace App\Filament\App\Resources\AssetTypes\Pages;

use App\Filament\App\Resources\AssetTypes\AssetTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetTypes extends ListRecords
{
    protected static string $resource = AssetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
