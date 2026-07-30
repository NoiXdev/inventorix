<?php

namespace App\Support\Assets;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\Person;
use App\Models\Place;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssetImport
{
    /**
     * @param  array<int, array<string, string>>  $rows  each keyed by canonical header
     * @return array{imported:int, failed: array<int, array{row:int, message:string}>}
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $failed = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is line 1; first data row is line 2
            try {
                DB::transaction(fn () => $this->importRow($row));
                $imported++;
            } catch (RowImportException $e) {
                $failed[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                $failed[] = ['row' => $rowNumber, 'message' => 'Unexpected error: '.$e->getMessage()];
            }
        }

        return ['imported' => $imported, 'failed' => $failed];
    }

    private function importRow(array $row): void
    {
        $get = fn (string $k): string => trim((string) ($row[$k] ?? ''));

        $id = $get('id');
        $asset = new Asset;
        if ($id !== '') {
            if (Asset::whereKey($id)->exists()) {
                throw new RowImportException("An asset with id [{$id}] already exists.");
            }
            $asset->{$asset->getKeyName()} = $id;
        }

        // required state
        $asset->state = $this->resolveEnum(AssetState::class, $get('state'), required: true);

        // required asset type
        $asset->asset_type_id = $this->firstOrCreateByName(AssetType::class, $get('asset_type'), required: true)->getKey();

        // manufacturer + model (model requires manufacturer)
        $modelName = $get('model');
        if ($modelName !== '') {
            $manufacturerName = $get('manufacturer');
            if ($manufacturerName === '') {
                throw new RowImportException('A model requires a manufacturer.');
            }
            $manufacturer = $this->firstOrCreateByName(Manufacturer::class, $manufacturerName);
            $model = AssetModel::query()
                ->where('manufacturer_id', $manufacturer->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($modelName)])
                ->first()
                ?? AssetModel::query()->create(['name' => $modelName, 'manufacturer_id' => $manufacturer->getKey()]);
            $asset->model_id = $model->getKey();
        }

        if (($place = $get('place')) !== '') {
            $asset->place_id = $this->firstOrCreateByName(Place::class, $place)->getKey();
        }
        if (($owner = $get('owner')) !== '') {
            $asset->owner_id = $this->resolveOwner($owner)->getKey();
        }

        $asset->serial_number = $get('serial_number') ?: null;
        $asset->buy_date = $this->parseDate($get('buy_date'));
        $asset->guarantee_end = $this->parseDate($get('guarantee_end'));
        $asset->buy_price = $get('buy_price') !== '' ? $get('buy_price') : null;
        if (($bt = $get('buy_type')) !== '') {
            $asset->buy_type = $this->resolveEnum(BuyType::class, $bt, required: false);
        }

        $asset->save();

        $tags = collect(explode(',', $get('tags')))->map(fn ($t) => trim($t))->filter()->values()->all();
        if ($tags !== []) {
            $asset->syncTags($tags);
        }
    }

    /** @param class-string $enumClass */
    private function resolveEnum(string $enumClass, string $value, bool $required): ?string
    {
        if ($value === '') {
            if ($required) {
                throw new RowImportException('Missing required value.');
            }

            return null;
        }
        if ($case = $enumClass::tryFrom($value)) {
            return $case->value;
        }
        foreach ($enumClass::cases() as $candidate) {
            if (mb_strtolower((string) $candidate->getLabel()) === mb_strtolower($value)) {
                return $candidate->value;
            }
        }
        throw new RowImportException("Unknown value [{$value}].");
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw new RowImportException("Invalid date [{$value}].");
        }
    }

    /** @param class-string<Model> $modelClass */
    private function firstOrCreateByName(string $modelClass, string $name, bool $required = false): Model
    {
        if ($name === '') {
            throw new RowImportException('Missing required value.');
        }

        return $modelClass::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? $modelClass::query()->create(['name' => $name]);
    }

    private function resolveOwner(string $name): Person
    {
        $person = Person::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($person) {
            return $person;
        }
        $parts = preg_split('/\s+/', $name, 2);
        $firstname = $parts[0] ?? $name;
        $lastname = ($parts[1] ?? '') !== '' ? $parts[1] : '-';

        return Person::query()->create([
            'name' => $name, 'firstname' => $firstname, 'lastname' => $lastname,
        ]);
    }
}
