<?php

namespace App\Filament\App\Resources\Assets\RelationManagers;

use App\Models\Asset;
use App\Models\Incident;
use App\Support\History\SummaryBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject'; // overridden by getTableQuery()

    public static function getTitle(Model $ownerRecord, string $pageClass): string
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

    public function add_noteAction(): Action
    {
        return Action::make('add_note')
            ->label(trans('history.add_note'))
            ->modalHeading(trans('history.add_note'))
            ->modalSubmitActionLabel(trans('history.add_note_save'))
            ->schema([
                Textarea::make('body')
                    ->label(trans('history.add_note_body'))
                    ->required()
                    ->minLength(1)
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(function (array $data): void {
                /** @var Asset $asset */
                $asset = $this->getOwnerRecord();

                activity('asset')
                    ->performedOn($asset)
                    ->causedBy(auth()->user())
                    ->withProperties(['body' => $data['body']])
                    ->log('note');
            });
    }

    protected function getTableQuery(): Builder
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        $incidentIds = Incident::query()
            ->where('asset_id', $asset->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return Activity::query()
            ->where(function (Builder $q) use ($asset, $incidentIds): void {
                $q->where(function (Builder $inner) use ($asset): void {
                    $inner->where('subject_type', Asset::class)
                        ->where('subject_id', $asset->id);
                });

                if (! empty($incidentIds)) {
                    $q->orWhere(function (Builder $inner) use ($incidentIds): void {
                        $inner->where('subject_type', Incident::class)
                            ->whereIn('subject_id', $incidentIds);
                    });
                }
            })
            ->where(function (Builder $q): void {
                $q->where('description', '!=', 'updated')
                    ->orWhereNotExists(function ($sub): void {
                        $sub->select(DB::raw(1))
                            ->from('activity_log as semantic')
                            ->whereColumn('semantic.subject_id', 'activity_log.subject_id')
                            ->whereColumn('semantic.subject_type', 'activity_log.subject_type')
                            ->whereIn('semantic.description', ['owner_changed', 'place_changed', 'state_changed'])
                            ->where(function ($causerMatch): void {
                                $causerMatch->whereColumn('semantic.causer_id', 'activity_log.causer_id')
                                    ->orWhere(function ($bothNull): void {
                                        $bothNull->whereNull('semantic.causer_id')
                                            ->whereNull('activity_log.causer_id');
                                    });
                            })
                            ->where(function ($timeBound): void {
                                $driver = DB::getDriverName();
                                $timeBound->whereRaw(match ($driver) {
                                    'sqlite' => 'ABS(strftime("%s", semantic.created_at) - strftime("%s", activity_log.created_at)) <= 1',
                                    'mysql', 'mariadb' => 'ABS(TIMESTAMPDIFF(SECOND, semantic.created_at, activity_log.created_at)) <= 1',
                                    'pgsql' => 'ABS(EXTRACT(EPOCH FROM semantic.created_at - activity_log.created_at)) <= 1',
                                    default => '1=1',
                                });
                            });
                    });
            });
    }

    public function table(Table $table): Table
    {
        $summary = new SummaryBuilder;

        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(trans('history.empty_state'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(trans('history.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('causer_label')
                    ->label(trans('history.column.user'))
                    ->state(function (Activity $record): string {
                        if ($record->causer_id === null) {
                            return trans('history.causer.system');
                        }
                        if ($record->causer === null) {
                            return trans('history.causer.former_user');
                        }

                        return (string) $record->causer->name;
                    }),

                TextColumn::make('description')
                    ->label(trans('history.column.event'))
                    ->formatStateUsing(fn (string $state) => Lang::has('history.event.'.$state) ? trans('history.event.'.$state) : $state)
                    ->badge(),

                TextColumn::make('summary')
                    ->label('')
                    ->state(fn (Activity $record) => $summary->forActivity($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event_kind')
                    ->label(trans('history.filter.event'))
                    ->multiple()
                    ->options([
                        'created' => trans('history.event.created'),
                        'updated' => trans('history.event.updated'),
                        'deleted' => trans('history.event.deleted'),
                        'note' => trans('history.event.note'),
                        'owner_changed' => trans('history.event.owner_changed'),
                        'place_changed' => trans('history.event.place_changed'),
                        'state_changed' => trans('history.event.state_changed'),
                        'handover_completed' => trans('handover.history.event.handover_completed'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        return empty($values) ? $query : $query->whereIn('description', $values);
                    }),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label(trans('history.filter.from')),
                        DatePicker::make('until')->label(trans('history.filter.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->headerActions([
                $this->add_noteAction(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
