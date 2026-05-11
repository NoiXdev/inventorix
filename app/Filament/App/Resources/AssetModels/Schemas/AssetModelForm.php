<?php

namespace App\Filament\App\Resources\AssetModels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class AssetModelForm
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

                                Select::make('manufacturer')
                                    ->label('Hersteller')
                                    ->searchable()
                                    ->relationship('manufacturer', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required()
                                            ->unique(),
                                    ])
                                    ->required()
                            ]),
                    ])
            ]);
    }
}
