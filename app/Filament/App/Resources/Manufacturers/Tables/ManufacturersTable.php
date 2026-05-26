<?php

namespace App\Filament\App\Resources\Manufacturers\Tables;

use App\Models\AssetModel;
use App\Models\Manufacturer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManufacturersTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('models_count')
                    ->label('Modele')
                    ->counts('models')
                    ->badge()
                    ->sortable(),

                TextColumn::make('assets_count')
                    ->label('Assets')
                    ->badge()
                    ->getStateUsing(static function (Manufacturer $record): int {
                        $counter = 0;
                        $record->models()->each(static function (AssetModel $model) use (&$counter) {
                            $counter += $model->assets()->count();
                        });

                        return $counter;
                    }),
            ])
            ->filters([
                //
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
