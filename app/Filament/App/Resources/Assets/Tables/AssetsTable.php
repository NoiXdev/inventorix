<?php

namespace App\Filament\App\Resources\Assets\Tables;

use App\Enums\AssetState;
use App\Filament\App\Resources\Assets\Actions\PrintQrAction;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Assets\Exporters\AssetExporter;
use App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction;
use App\Models\Asset;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
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

                TextColumn::make('model.manufacturer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('incidents_count')
                    ->toggleable()
                    ->counts('incidents')
                    ->badge()
                    ->label('Vorfälle')
                    ->toggledHiddenByDefault(),

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

                TextColumn::make('buy_price')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable()
                    ->label('Kaufpreis'),
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

                Filter::make('serial_number')
                    ->schema([
                        TextInput::make('serial_number')
                            ->label('Seriennummer'),
                    ])
                    ->indicateUsing(static function (array $data): ?string {
                        if (filled($data['serial_number'] ?? null)) {
                            return 'Seriennummer: '.$data['serial_number'];
                        }

                        return null;
                    })
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['serial_number'] ?? null),
                            static fn (Builder $query): Builder => $query->where('serial_number', 'like', '%'.$data['serial_number'].'%'),
                        );
                    }),

                Filter::make('buy_price_null')
                    ->schema([
                        Toggle::make('show_empty_price')
                            ->label('Zeige Ohne Preise'),
                    ])
                    ->indicateUsing(static function (array $data) {
                        if ((bool) $data['show_empty_price']) {
                            return 'Zeige Ohne Preise';
                        }
                    })
                    ->query(static function (Builder $query, array $data): Builder {
                        if ((bool) $data['show_empty_price']) {
                            $query->where('buy_price', '=', null);
                            $query->orWhere('buy_price', '=', '');
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    HandoverWizardAction::make(
                        'handover',
                        fn (Asset $record): array => [$record->id],
                    ),
                    PrintQrAction::single(),
                    ReplicateAction::make()
                        ->requiresConfirmation()
                        ->form([
                            TextInput::make('new_id')
                                ->required()
                                ->visible(static function (Get $get) {
                                    return ! (bool) $get('override_id');
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
                            if (isset($data['new_id']) && ! empty($data['new_id'])) {
                                $replica->id = $data['new_id'];
                            }
                        })
                        ->excludeAttributes([
                            'invoice',
                        ]),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    HandoverWizardAction::make(
                        'handover_bulk',
                        fn ($livewire) => $livewire->getSelectedTableRecords()->pluck('id')->all(),
                    )->label(trans('handover.action.bulk')),
                    PrintQrAction::bulk(),
                    ExportBulkAction::make()
                        ->exporter(AssetExporter::class)
                        ->modifyQueryUsing(fn (Builder $query) => $query->with(['assetType', 'model.manufacturer', 'owner', 'place', 'tags'])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
