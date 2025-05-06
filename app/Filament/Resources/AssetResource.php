<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $slug = 'assets';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return __('menu.assets');
    }

    public static function getNavigationBadge(): ?string
    {
        return Asset::all()->count();
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
                            ->content(fn(?Asset $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                        Placeholder::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->content(fn(?Asset $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                    ]),


                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Allgemein')
                            ->columns(3)
                            ->schema([
                                Select::make('asset_type_id')
                                    ->label('Type')
                                    ->relationship('assetType', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->required(),

                                TextInput::make('serial_number')
                                    ->columnSpan(2)
                                    ->label('Seriennummer'),

                                Select::make('manufacturer_id')
                                    ->label("Hersteller")
                                    ->relationship('manufacturer', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->required(),

                                Select::make('model_id')
                                    ->relationship('model', 'name')
                                    ->label('Modell')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->required(),

                                Select::make('owner_id')
                                    ->label("Aktueller Besitzer")
                                    ->preload()
                                    ->relationship('owner', 'name')
                                    ->searchable(),
                            ]),

                        Tabs\Tab::make('Kauf Informationen')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('buy_date')
                                    ->label("Kaufdatum"),

                                DatePicker::make('guarantee_end')
                                    ->label("Garantie Ende")
                                    ->nullable(),

                                FileUpload::make('invoice'),
                            ]),

                        Tabs\Tab::make('Tags')
                            ->columns(2)
                            ->schema([
                                SpatieTagsInput::make('tags')
                                    ->label('')
                                    ->columnSpanFull()
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assetType.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('manufacturer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model.name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('serial_number'),
            ])
            ->filters([
                SelectFilter::make('asset_type_id')
                    ->multiple()
                    ->relationship('assetType', 'name'),

                SelectFilter::make('manufacturer_id')
                    ->multiple()
                    ->relationship('manufacturer', 'name'),

                SelectFilter::make('owner_id')
                    ->multiple()
                    ->relationship('owner', 'name'),
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
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['assetType', 'manufacturer', 'owner']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['assetType.name', 'manufacturer.name', 'owner.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->assetType) {
            $details['AssetType'] = $record->assetType->name;
        }

        if ($record->manufacturer) {
            $details['Manufacturer'] = $record->manufacturer->name;
        }

        if ($record->owner) {
            $details['Owner'] = $record->owner->name;
        }

        return $details;
    }
}
