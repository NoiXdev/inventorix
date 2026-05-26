<?php

namespace App\Filament\App\Resources\Assets\Schemas;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetModel;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columns(3)
                    ->hiddenOn('create')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Erstellt am')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Letzte Änderung am')
                            ->dateTime(),

                        View::make('forms.components.qr-code'),
                    ]),

                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Allgemein')
                            ->columns(3)
                            ->schema([

                                Hidden::make('id')
                                    ->default(fn () => request()->get('forceId'))
                                    ->hiddenOn('edit'),

                                Select::make('state')
                                    ->label('State')
                                    ->preload()
                                    ->enum(AssetState::class)
                                    ->options(AssetState::class)
                                    ->searchable()
                                    ->required(),

                                Select::make('asset_type_id')
                                    ->label('Type')
                                    ->relationship('assetType', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->required(),

                                Select::make('owner_id')
                                    ->label('Aktueller Besitzer')
                                    ->preload()
                                    ->relationship('owner', 'name')
                                    ->createOptionForm([
                                        TextInput::make('firstname')
                                            ->label('Vorname')
                                            ->required(),

                                        TextInput::make('lastname')
                                            ->label('Nachname')
                                            ->required(),
                                    ])
                                    ->searchable(),

                                Select::make('place_id')
                                    ->label('Ort')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ])
                                    ->preload()
                                    ->relationship('place', 'name')
                                    ->searchable(),
                            ]),

                        Tabs\Tab::make('Informationen')
                            ->columns(2)
                            ->schema([

                                TextInput::make('serial_number')
                                    ->columnSpan(2)
                                    ->columnSpanFull()
                                    ->label('Seriennummer'),

                                Select::make('model_id')
                                    ->relationship('model', 'name')
                                    ->label('Modell')
                                    ->searchable()
                                    ->getSearchResultsUsing(static function (string $search) {
                                        $items = [];
                                        AssetModel::where('name', 'like', '%'.$search.'%')
                                            ->orWhereHas('manufacturer', function (Builder $query) use ($search) {
                                                $query->where('name', 'like', '%'.$search.'%');
                                            })
                                            ->limit(50)
                                            ->each(static function (AssetModel $model) use (&$items) {
                                                $items[$model->id] = '('.$model->manufacturer->name.') '.$model->name;
                                            });

                                        return $items;
                                    })
                                    ->getOptionLabelUsing(static function ($value): string {
                                        $model = AssetModel::find($value);

                                        return '('.$model->manufacturer->name.') '.$model->name;
                                    })
                                    ->createOptionForm([

                                        Select::make('manufacturer')
                                            ->relationship('manufacturer', 'name')
                                            ->label('Hersteller')
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Name')
                                                    ->required()
                                                    ->unique(),
                                            ])
                                            ->searchable(),

                                        TextInput::make('name')
                                            ->label('Name')
                                            ->required(),
                                    ]),

                            ]),

                        Tabs\Tab::make('Kauf Informationen')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('buy_date')
                                    ->label('Kaufdatum'),

                                DatePicker::make('guarantee_end')
                                    ->label('Garantie Ende')
                                    ->nullable(),

                                TextInput::make('buy_price')
                                    // ->decimals(2)
                                    ->nullable()
                                    ->label('Kaufpreis / Mietpreis'),

                                Select::make('buy_type')
                                    ->label('Kaufart')
                                    ->options(BuyType::class)
                                    ->enum(BuyType::class),

                                FileUpload::make('invoice'),
                            ]),

                        Tabs\Tab::make('Tags')
                            ->columns()
                            ->schema([
                                SpatieTagsInput::make('tags')
                                    ->label('')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Vorfälle')
                            ->hiddenOn('create')
                            ->columns(1)
                            ->badge(static function (?Asset $record): int {
                                return $record?->incidents()->count() ?? 0;
                            })
                            ->schema([

                                Repeater::make('incidents')
                                    ->relationship('incidents')
                                    ->label('Vorfälle')
                                    ->addActionLabel('Vorfall hinzufügen')
                                    ->columns(1)
                                    ->minItems(0)
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(static function (array $state): string {
                                        $label = [];

                                        if (! isset($state['open_date']) && ! isset($state['closed_date'])) {
                                            $label[] = '[Neu]';
                                        }

                                        if (isset($state['open_date']) && ! isset($state['closed_date'])) {
                                            $label[] = '[Offen]';
                                        }

                                        if (isset($state['open_date'], $state['closed_date'])) {
                                            $label[] = '[Geschlossen]';
                                        }

                                        if (isset($state['open_date'])) {
                                            $label[] = Carbon::parse($state['open_date'])->format('d.m.Y');
                                        }

                                        if (isset($state['title'])) {
                                            $label[] = $state['title'];
                                        }

                                        return implode(', ', $label);
                                    })
                                    ->schema([

                                        Grid::make(3)
                                            ->schema([
                                                DateTimePicker::make('open_date')
                                                    ->label('Vorfalls Datum')
                                                    ->required(),

                                                DateTimePicker::make('closed_date')
                                                    ->nullable()
                                                    ->label('Lösung Datum'),
                                            ]),

                                        TextInput::make('title')
                                            ->label('Titel')
                                            ->required(),

                                        Textarea::make('notes')
                                            ->label('Notizen')
                                            ->columnSpanFull()
                                            ->nullable(),
                                    ]),

                            ]),
                    ]),
            ]);
    }
}
