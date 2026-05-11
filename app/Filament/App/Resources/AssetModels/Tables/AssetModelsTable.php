<?php

namespace App\Filament\App\Resources\AssetModels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssetModelsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('manufacturer.name')
                    ->label('Hersteller')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assets_count')
                    ->label('Assets')
                    ->counts('assets')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('manufacturer')
                    ->relationship('manufacturer', 'name')
                    ->multiple()
                    ->label('Hersteller'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
