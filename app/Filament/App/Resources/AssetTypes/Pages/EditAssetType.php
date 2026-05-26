<?php

namespace App\Filament\App\Resources\AssetTypes\Pages;

use App\Filament\App\Resources\AssetTypes\AssetTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetType extends EditRecord
{
    protected static string $resource = AssetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
