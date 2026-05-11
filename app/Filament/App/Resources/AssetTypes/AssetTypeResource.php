<?php

namespace App\Filament\App\Resources\AssetTypes;

use App\Filament\App\Resources\AssetTypes\Schemas\AssetTypeForm;
use App\Filament\App\Resources\AssetTypes\Schemas\AssetTypeInfolist;
use App\Filament\App\Resources\AssetTypes\Tables\AssetTypesTable;
use App\Models\AssetType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssetTypeResource extends Resource
{
    protected static ?string $model = AssetType::class;

    protected static ?string $slug = 'asset-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('menu.assets-support');
    }

    public static function getNavigationBadge(): ?string
    {
        return AssetType::all()->count();
    }

    public static function getPluralLabel(): ?string
    {
        return __('type.label-plural');
    }

    public static function getLabel(): ?string
    {
        return __('type.label');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetTypesTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\App\Resources\AssetTypes\Pages\ListAssetTypes::route('/'),
            'create' => \App\Filament\App\Resources\AssetTypes\Pages\CreateAssetType::route('/create'),
            'edit' => \App\Filament\App\Resources\AssetTypes\Pages\EditAssetType::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
