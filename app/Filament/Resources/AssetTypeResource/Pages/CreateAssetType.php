<?php

namespace App\Filament\Resources\AssetTypeResource\Pages;

use App\Filament\Resources\AssetTypeResource;
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
