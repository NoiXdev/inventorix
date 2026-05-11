<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Filament\App\Resources\Assets\AssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
