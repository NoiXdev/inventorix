<?php

namespace App\Filament\App\Resources\Handovers\Tables;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class HandoversTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_at', 'desc')
            ->columns([
                TextColumn::make('signed_at')
                    ->label(trans('handover.list.column.signed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(trans('handover.list.column.type'))
                    ->badge(),
                TextColumn::make('recipient_name')
                    ->label(trans('handover.list.column.recipient'))
                    ->description(fn ($record) => $record->recipient_kind->getLabel()),
                TextColumn::make('assets_count')
                    ->label(trans('handover.list.column.asset_count'))
                    ->counts('assets')
                    ->numeric(),
                TextColumn::make('createdBy.name')
                    ->label(trans('handover.list.column.created_by'))
                    ->toggleable(),
                TextColumn::make('pdf_path')
                    ->label(trans('handover.list.column.pdf'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? trans('handover.list.pdf_download')
                        : trans('handover.list.pdf_pending')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(HandoverType::cases())->mapWithKeys(
                        fn (HandoverType $t) => [$t->value => $t->getLabel()]
                    )->all()),
                SelectFilter::make('recipient_kind')
                    ->options(collect(RecipientKind::cases())->mapWithKeys(
                        fn (RecipientKind $k) => [$k->value => $k->getLabel()]
                    )->all()),
                SelectFilter::make('created_by')
                    ->relationship('createdBy', 'name'),
                Filter::make('signed_at_range')
                    ->schema([
                        DatePicker::make('from')->label(trans('history.filter.from')),
                        DatePicker::make('until')->label(trans('history.filter.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('signed_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('signed_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download_pdf')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)
                    ->label(trans('handover.list.pdf_download'))
                    ->visible(fn ($record): bool => $record->pdf_path !== null)
                    ->url(fn ($record): string => URL::temporarySignedRoute(
                        'handover.pdf',
                        now()->addMinutes(5),
                        ['handover' => $record->id],
                    )),
            ])
            ->toolbarActions([]);
    }
}
