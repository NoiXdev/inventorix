<?php

namespace App\Filament\App\Resources\AssetModels\Pages;

use App\Filament\App\Resources\AssetModels\AssetModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetModel extends EditRecord
{
    protected static string $resource = AssetModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
