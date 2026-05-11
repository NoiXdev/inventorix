<?php

namespace App\Filament\App\Resources\AssetTypes\Schemas;

use App\Models\AssetType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class AssetTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columns()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Erstellt am')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->dateTime(),
                    ]),

                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Allgemein')
                            ->columns()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required(),
                            ]),
                    ])
            ]);
    }
}
