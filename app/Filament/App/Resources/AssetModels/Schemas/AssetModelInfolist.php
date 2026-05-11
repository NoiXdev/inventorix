<?php

namespace App\Filament\App\Resources\AssetModels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AssetModelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('Id'),

                TextEntry::make('name')
                    ->label('Name'),

                TextEntry::make('created_at')
                    ->label('Created Date')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Last Modified Date')
                    ->dateTime(),

                TextEntry::make('manufacturer.name')
                    ->label('Manufacturer Id'),
            ]);
    }
}
