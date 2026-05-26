<?php

namespace App\Filament\App\Resources\Places\Pages;

use App\Filament\App\Resources\Places\PlaceResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlace extends CreateRecord
{
    protected static string $resource = PlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
