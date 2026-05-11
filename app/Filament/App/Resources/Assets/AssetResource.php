<?php

namespace App\Filament\App\Resources\Assets;

use App\Filament\App\Resources\Assets\Pages\CreateAsset;
use App\Filament\App\Resources\Assets\Pages\EditAsset;
use App\Filament\App\Resources\Assets\Pages\ListAssets;
use App\Filament\App\Resources\Assets\RelationManagers\HistoryRelationManager;
use App\Filament\App\Resources\Assets\RelationManagers\IncidentsRelationManager;
use App\Filament\App\Resources\Assets\Schemas\AssetForm;
use App\Filament\App\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\App\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $slug = 'assets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 0;

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

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::table($table);
    }

    public static function getRelations(): array
    {
        return [
            IncidentsRelationManager::class,
            HistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Asset>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['assetType', 'model', 'owner', 'place']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['assetType.name', 'model.name', 'owner.name', 'place.name'];
    }

    /**
     * @param  Asset  $record
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->assetType) {
            $details['AssetType'] = $record->assetType->name;
        }

        if ($record->model) {
            $details['Model'] = $record->model->name;
        }

        if ($record->owner) {
            $details['Owner'] = $record->owner->name;
        }

        if ($record->place) {
            $details['Place'] = $record->place->name;
        }

        return $details;
    }
}
