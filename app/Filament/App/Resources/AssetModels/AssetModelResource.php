<?php

namespace App\Filament\App\Resources\AssetModels;

use App\Filament\App\Resources\AssetModels\Pages\CreateAssetModel;
use App\Filament\App\Resources\AssetModels\Pages\EditAssetModel;
use App\Filament\App\Resources\AssetModels\Pages\ListAssetModels;
use App\Filament\App\Resources\AssetModels\RelationManagers\AssetsRelationManager;
use App\Filament\App\Resources\AssetModels\Schemas\AssetModelForm;
use App\Filament\App\Resources\AssetModels\Schemas\AssetModelInfolist;
use App\Filament\App\Resources\AssetModels\Tables\AssetModelsTable;
use App\Models\AssetModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetModelResource extends Resource
{
    protected static ?string $model = AssetModel::class;

    protected static ?string $slug = 'asset-models';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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

    public static function form(Schema $schema): Schema
    {
        return AssetModelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetModelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetModelsTable::table($table);
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetModels::route('/'),
            'create' => CreateAssetModel::route('/create'),
            'edit' => EditAssetModel::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<AssetModel>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['manufacturer']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'manufacturer.name'];
    }

    /**
     * @param  AssetModel  $record
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->manufacturer) {
            $details['Manufacturer'] = $record->manufacturer->name;
        }

        return $details;
    }
}
