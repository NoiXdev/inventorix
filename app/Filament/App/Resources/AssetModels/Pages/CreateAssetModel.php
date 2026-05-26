<?php

namespace App\Filament\App\Resources\AssetModels\Pages;

use App\Filament\App\Resources\AssetModels\AssetModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetModel extends CreateRecord
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
