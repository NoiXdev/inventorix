<?php

namespace App\Filament\App\Resources\AssetTypes\Pages;

use App\Filament\App\Resources\AssetTypes\AssetTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetType extends CreateRecord
{
    protected static string $resource = AssetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
