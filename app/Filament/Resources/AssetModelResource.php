<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetModelResource\Pages;
use App\Models\AssetModel;
use App\Models\AssetType;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetModelResource extends Resource
{
    protected static ?string $model = AssetModel::class;

    protected static ?string $slug = 'asset-models';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('menu.assets-support');
    }

    public static function getPluralLabel(): ?string
    {
        return __('models.label-plural');
    }

    public static function getLabel(): ?string
    {
        return __('models.label');
    }

    public static function getNavigationBadge(): ?string
    {
        return AssetModel::all()->count();
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns()
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Erstellt am')
                            ->content(fn(?AssetModel $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                        Placeholder::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->content(fn(?AssetModel $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
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
            'index' => Pages\ListAssetTypes::route('/'),
            'create' => Pages\CreateAssetType::route('/create'),
            'edit' => Pages\EditAssetType::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
