<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManufacturers extends ListRecords
{
    protected static string $resource = ManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
