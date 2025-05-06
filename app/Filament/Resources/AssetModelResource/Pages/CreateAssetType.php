<?php

namespace App\Filament\Resources\AssetModelResource\Pages;

use App\Filament\Resources\AssetModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetType extends CreateRecord
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
