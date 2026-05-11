<?php

namespace App\Filament\App\Resources\Manufacturers\Pages;

use App\Filament\App\Resources\Manufacturers\ManufacturerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManufacturer extends CreateRecord
{
    protected static string $resource = ManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
