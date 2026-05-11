<?php

namespace App\Filament\App\Resources\Users\Schemas;

use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Novadaemon\FilamentCombobox\Combobox;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->hiddenOn('create')
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
                                TextInput::make('firstname')
                                    ->label('Vorname')
                                    ->required(),

                                TextInput::make('lastname')
                                    ->label('Nachname')
                                    ->required(),

                                TextInput::make('name')
                                    ->label('Anzeigename')
                                    ->columnSpanFull()
                                    ->hiddenOn('create')
                                    ->suffixAction(
                                        Action::make('created_name')
                                            ->icon('heroicon-o-arrow-path')
                                            ->requiresConfirmation()
                                            ->action(function (Set $set, Get $get) {
                                                $set('name', $get('firstname') . ' ' . $get('lastname'));
                                            })
                                    )
                                    ->required(),

                            ]),

                        Tabs\Tab::make('Login')
                            ->columns()
                            ->schema([

                                Toggle::make('login_enabled')
                                    ->live()
                                    ->columnSpanFull()
                                    ->label('Login in dieses Panel aktivieren'),

                                TextInput::make('email')
                                    ->label('E-Mail Adresse')
                                    ->visible(static function (Get $get) {
                                        return (bool)$get('login_enabled');
                                    })
                                    ->email()
                                    ->required(),

                                TextInput::make('password')
                                    ->label('Passwort')
                                    ->visible(static function (Get $get) {
                                        return (bool)$get('login_enabled');
                                    })
                                    ->hiddenOn('edit')
                                    ->required(),
                            ]),

                        Tabs\Tab::make('Assets')
                            ->columns()
                            ->schema([
                                Combobox::make('assets')
                                    ->multiple()
                                    ->label('')
                                    ->columnSpanFull()
                                    ->height('500px')
                                    ->relationship('assets', 'id')
                                    ->getOptionLabelFromRecordUsing(static function (Asset $asset) {
                                        return '(' . $asset->model->manufacturer->name . ') ' . $asset->model->name;
                                    })
                                    ->searchable()
                                    ->required(),
                            ]),
                    ])
            ]);
    }
}
