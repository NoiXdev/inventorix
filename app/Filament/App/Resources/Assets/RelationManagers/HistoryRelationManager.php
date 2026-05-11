<?php

namespace App\Filament\App\Resources\Assets\RelationManagers;

use App\Models\Asset;
use App\Support\History\SummaryBuilder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject'; // overridden by getTableQuery()

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return trans('history.tab');
    }

    public static function getNavigationIcon(): string
    {
        return Heroicon::OutlinedClock->value;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getTableQuery(): Builder
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        return Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id);
    }

    public function table(Table $table): Table
    {
        $summary = new SummaryBuilder();

        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(trans('history.empty_state'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(trans('history.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->default(trans('history.causer.system')),

                TextColumn::make('description')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state) => \Illuminate\Support\Facades\Lang::has('history.event.' . $state) ? trans('history.event.' . $state) : $state)
                    ->badge(),

                TextColumn::make('summary')
                    ->label('')
                    ->state(fn (Activity $record) => $summary->forActivity($record))
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
