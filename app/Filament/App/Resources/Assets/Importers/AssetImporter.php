<?php

namespace App\Filament\App\Resources\Assets\Importers;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Throwable;

class AssetImporter extends Importer
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->label('ID')
                ->fillRecordUsing(fn () => null), // handled in resolveRecord()

            ImportColumn::make('state')
                ->requiredMapping()
                ->rules(['required'])
                ->castStateUsing(fn (?string $state): ?string => self::resolveEnumValue(AssetState::class, $state)),

            ImportColumn::make('asset_type')
                ->label('Asset-Typ')
                ->requiredMapping()
                ->rules(['required'])
                ->fillRecordUsing(function (Asset $record, ?string $state): void {
                    if (blank($state)) {
                        return;
                    }
                    $record->asset_type_id = self::firstOrCreateByName(AssetType::class, $state)->getKey();
                }),

            ImportColumn::make('serial_number'),

            ImportColumn::make('buy_date')
                ->castStateUsing(fn (?string $state): ?string => self::parseDate($state)),

            ImportColumn::make('guarantee_end')
                ->castStateUsing(fn (?string $state): ?string => self::parseDate($state)),

            ImportColumn::make('buy_price'),

            ImportColumn::make('buy_type')
                ->castStateUsing(fn (?string $state): ?string => self::resolveEnumValue(BuyType::class, $state)),
        ];
    }

    public function resolveRecord(): ?Asset
    {
        $id = trim((string) ($this->data['id'] ?? ''));

        if ($id !== '' && Asset::query()->whereKey($id)->exists()) {
            throw new RowImportFailedException("Ein Asset mit der ID [{$id}] existiert bereits.");
        }

        $asset = new Asset();

        if ($id !== '') {
            $asset->{$asset->getKeyName()} = $id;
        }

        return $asset;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        // Note: do NOT use str()->plural() — it pluralises in English ("Zeiles").
        $body = 'Der Asset-Import wurde abgeschlossen: '
            . number_format($import->successful_rows) . ' '
            . ($import->successful_rows === 1 ? 'Zeile' : 'Zeilen') . ' importiert.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . ($failedRowsCount === 1 ? 'Zeile' : 'Zeilen') . ' fehlgeschlagen.';
        }

        return $body;
    }

    /**
     * Accept either the backed enum value (e.g. "in-use") or its German label
     * (e.g. "In Benutzung"). Returns the enum's backing value.
     *
     * @param  class-string  $enumClass
     */
    protected static function resolveEnumValue(string $enumClass, ?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $state = trim($state);

        if ($case = $enumClass::tryFrom($state)) {
            return $case->value;
        }

        foreach ($enumClass::cases() as $candidate) {
            if (mb_strtolower((string) $candidate->getLabel()) === mb_strtolower($state)) {
                return $candidate->value;
            }
        }

        throw new RowImportFailedException("Unbekannter Wert [{$state}].");
    }

    protected static function parseDate(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        try {
            return Carbon::parse(trim($state))->toDateString();
        } catch (Throwable) {
            throw new RowImportFailedException("Ungültiges Datum [{$state}].");
        }
    }

    /**
     * Match a lookup record by name (case-insensitive, trimmed) or create it.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected static function firstOrCreateByName(string $modelClass, string $name): Model
    {
        $name = trim($name);

        return $modelClass::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? $modelClass::query()->create(['name' => $name]);
    }
}
