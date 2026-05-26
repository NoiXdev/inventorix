<?php

namespace App\Filament\App\Resources\Assets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('Id'),

                TextEntry::make('state')
                    ->label('State'),

                TextEntry::make('assetType.name')
                    ->label('Asset Type Id'),

                TextEntry::make('owner.name')
                    ->label('Owner Id'),

                TextEntry::make('place.name')
                    ->label('Place Id'),

                TextEntry::make('model.name')
                    ->label('Model Id'),

                TextEntry::make('serial_number')
                    ->label('Serial Number'),

                TextEntry::make('buy_date')
                    ->label('Buy Date')
                    ->dateTime(),

                TextEntry::make('buy_type')
                    ->label('Buy Type'),

                TextEntry::make('buy_price')
                    ->label('Buy Price'),

                TextEntry::make('guarantee_end')
                    ->label('Guarantee End')
                    ->dateTime(),

                TextEntry::make('invoice')
                    ->label('Invoice'),

                TextEntry::make('created_at')
                    ->label('Created Date')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Last Modified Date')
                    ->dateTime(),
            ]);
    }
}
