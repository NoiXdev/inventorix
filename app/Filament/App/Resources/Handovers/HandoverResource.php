<?php

namespace App\Filament\App\Resources\Handovers;

use App\Filament\App\Resources\Handovers\Pages\ListHandovers;
use App\Filament\App\Resources\Handovers\Pages\ViewHandover;
use App\Filament\App\Resources\Handovers\Schemas\HandoverInfolist;
use App\Filament\App\Resources\Handovers\Tables\HandoversTable;
use App\Models\Handover;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HandoverResource extends Resource
{
    protected static ?string $model = Handover::class;

    protected static ?string $slug = 'handovers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return trans('handover.nav.group');
    }

    public static function getLabel(): ?string
    {
        return trans('handover.resource.label');
    }

    public static function getPluralLabel(): ?string
    {
        return trans('handover.resource.plural');
    }

    public static function table(Table $table): Table
    {
        return HandoversTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HandoverInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHandovers::route('/'),
            'view'  => ViewHandover::route('/{record}'),
        ];
    }

    /** @return array<string, mixed> */
    public static function getRelations(): array
    {
        return [];
    }
}
