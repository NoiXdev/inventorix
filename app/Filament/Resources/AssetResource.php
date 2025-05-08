<?php

namespace App\Filament\Resources;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pelmered\FilamentMoneyField\Forms\Components\MoneyInput;
use Pelmered\FilamentMoneyField\Tables\Columns\MoneyColumn;

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

    public static function getLabel(): ?string
    {
        if (request()->has('replicated')) {
            return __('asset.label-copy');
        }
        return __('asset.label');
    }

    public static function getPluralLabel(): ?string
    {
        return __('asset.label-plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return Asset::all()->count();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('state')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assetType.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('manufacturer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->toggleable()
                    ->searchable()
                    ->toggledHiddenByDefault()
                    ->sortable(),

                TextColumn::make('model.name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('serial_number')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label('Seriennummer'),

                MoneyColumn::make('buy_price')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable()
                    ->label('Kaufpreis')
            ])
            ->filters([
                SelectFilter::make('state')
                    ->multiple()
                    ->searchable()
                    ->options(AssetState::class),

                SelectFilter::make('asset_type_id')
                    ->multiple()
                    ->searchable()
                    ->relationship('assetType', 'name'),

                SelectFilter::make('manufacturer_id')
                    ->multiple()
                    ->searchable()
                    ->relationship('manufacturer', 'name'),

                SelectFilter::make('owner_id')
                    ->multiple()
                    ->searchable()
                    ->relationship('owner', 'name'),

                Filter::make('buy_price_null')
                    ->form([
                        Toggle::make('show_empty_price')
                            ->label('Zeige Ohne Preise')
                    ])
                    ->indicateUsing(static function (array $data) {
                        if ((bool)$data['show_empty_price']) {
                            return 'Zeige Ohne Preise';
                        }
                    })
                    ->query(static function (Builder $query, array $data): Builder {
                        if ((bool)$data['show_empty_price']) {
                            $query->where('buy_price', '=', null);
                            $query->orWhere('buy_price', '=', '');
                        }
                        return $query;
                    })
            ])
            ->actions([

                ActionGroup::make([
                    ReplicateAction::make()
                        ->requiresConfirmation()
                        ->form([
                            TextInput::make('new_id')
                                ->required()
                                ->visible(static function (Get $get) {
                                    return !(bool)$get('override_id');
                                })
                                ->label('Override ID'),

                            Toggle::make('override_id')
                                ->label('Create new ID')
                                ->live(),
                        ])
                        ->successRedirectUrl(static function (Asset $replica) {
                            return AssetResource::getUrl('edit', ['record' => $replica, 'replicated' => true]);
                        })
                        ->beforeReplicaSaved(static function (Asset $replica, array $data): void {
                            if (isset($data['new_id']) && !empty($data['new_id'])) {
                                $replica->id = $data['new_id'];
                            }
                        })
                        ->excludeAttributes([
                            'invoice'
                        ]),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Grid::make()
                    ->columns(3)
                    ->hiddenOn('create')
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Erstellt am')
                            ->content(fn(?Asset $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                        Placeholder::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->content(fn(?Asset $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                        View::make('forms.components.qr-code')
                            ->label('QR Code'),
                    ]),


                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Allgemein')
                            ->columns(3)
                            ->schema([

                                Hidden::make('id')
                                    ->default(fn() => request()->get('forceId'))
                                    ->hiddenOn('edit'),

                                Select::make('state')
                                    ->label('State')
                                    ->preload()
                                    ->enum(AssetState::class)
                                    ->options(AssetState::class)
                                    ->searchable()
                                    ->required(),

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

                                TextInput::make('serial_number')
                                    ->columnSpan(2)
                                    ->columnSpanFull()
                                    ->label('Seriennummer'),

                                Select::make('model_id')
                                    ->relationship('model', 'name')
                                    ->label('Modell')
                                    ->searchable()
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
                                    ->createOptionForm([
                                        TextInput::make('firstname')
                                            ->label('Vorname')
                                            ->required(),

                                        TextInput::make('lastname')
                                            ->label('Nachname')
                                            ->required(),
                                    ])
                                    ->searchable(),

                                Select::make('place_id')
                                    ->label("Ort")
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->preload()
                                    ->relationship('place', 'name')
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

                                MoneyInput::make('buy_price')->decimals(2)
                                    ->nullable()
                                    ->label('Kaufpreis / Mietpreis'),

                                Select::make('buy_type')
                                    ->label('Kaufart')
                                    ->options(BuyType::class)
                                    ->enum(BuyType::class),

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
