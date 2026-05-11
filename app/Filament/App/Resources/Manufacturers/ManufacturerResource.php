<?php

namespace App\Filament\App\Resources\Manufacturers;

use App\Filament\App\Resources\Manufacturers\RelationManagers\ModelsRelationManager;
use App\Filament\App\Resources\Manufacturers\Schemas\ManufacturerForm;
use App\Filament\App\Resources\Manufacturers\Schemas\ManufacturerInfolist;
use App\Filament\App\Resources\Manufacturers\Tables\ManufacturersTable;
use App\Models\Manufacturer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static ?string $slug = 'manufacturers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('menu.assets-support');
    }

    public static function getNavigationBadge(): ?string
    {
        return Manufacturer::all()->count();
    }

    public static function getLabel(): ?string
    {
        return __('manufacturer.label');
    }

    public static function form(Schema $schema): Schema
    {
        return ManufacturerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ManufacturerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManufacturersTable::table($table);
    }

    public static function getRelations(): array
    {
        return [
            ModelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturers::route('/'),
            'create' => Pages\CreateManufacturer::route('/create'),
            'edit' => Pages\EditManufacturer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
