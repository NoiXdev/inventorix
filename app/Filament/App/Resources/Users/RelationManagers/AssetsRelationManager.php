<?php

namespace App\Filament\App\Resources\Users\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('state')
                    ->label('State')
                    ->required(),

                Select::make('asset_type_id')
                    ->label('Asset Type Id')
                    ->relationship('assetType', 'name')
                    ->searchable()
                    ->required(),

                Select::make('place_id')
                    ->label('Place Id')
                    ->relationship('place', 'name')
                    ->searchable(),

                Select::make('model_id')
                    ->label('Model Id')
                    ->relationship('model', 'name')
                    ->searchable(),

                TextInput::make('serial_number')
                    ->label('Serial Number'),

                DatePicker::make('buy_date')
                    ->label('Buy Date'),

                TextInput::make('buy_type')
                    ->label('Buy Type'),

                TextInput::make('buy_price')
                    ->label('Buy Price')
                    ->numeric(),

                DatePicker::make('guarantee_end')
                    ->label('Guarantee End'),

                TextInput::make('invoice')
                    ->label('Invoice'),

                TextEntry::make('created_at')
                    ->label('Created Date')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Last Modified Date')
                    ->dateTime(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('Id'),

                TextEntry::make('state')
                    ->label('State'),

                TextEntry::make('assetType.name')
                    ->label('Asset Type Id'),

                TextEntry::make('place.name')
                    ->label('Place Id'),

                TextEntry::make('model.name')
                    ->label('Model Id'),

                TextEntry::make('serial_number')
                    ->label('Serial Number'),

                TextEntry::make('buy_date')
                    ->label('Buy Date')
                    ->dateTime(),

                TextEntry::make('buy_type')
                    ->label('Buy Type'),

                TextEntry::make('buy_price')
                    ->label('Buy Price'),

                TextEntry::make('guarantee_end')
                    ->label('Guarantee End')
                    ->dateTime(),

                TextEntry::make('invoice')
                    ->label('Invoice'),

                TextEntry::make('created_at')
                    ->label('Created Date')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->label('Last Modified Date')
                    ->dateTime(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('state')
                    ->label('State'),

                TextColumn::make('assetType.name')
                    ->label('Asset Type Id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('place.name')
                    ->label('Place Id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model.name')
                    ->label('Model Id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serial_number')
                    ->label('Serial Number'),

                TextColumn::make('buy_date')
                    ->label('Buy Date')
                    ->date(),

                TextColumn::make('buy_type')
                    ->label('Buy Type'),

                TextColumn::make('buy_price')
                    ->label('Buy Price'),

                TextColumn::make('guarantee_end')
                    ->label('Guarantee End')
                    ->date(),

                TextColumn::make('invoice')
                    ->label('Invoice'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
