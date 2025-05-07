<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = "Mitarbeiter";

    protected static ?string $pluralModelLabel = "Mitarbeiter";

    protected static ?int $navigationSort = 500;

    public static function getNavigationGroup(): ?string
    {
        return __('menu.general');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->hiddenOn('create')
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Erstellt am')
                            ->content(fn(?User $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                        Placeholder::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->content(fn(?User $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
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
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Anzeigename')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('firstname')
                    ->label('Vorname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lastname')
                    ->label('Nachname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-Mail Adresse')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }
}
