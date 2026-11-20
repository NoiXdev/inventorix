<?php

namespace App\Support\Assets;

use App\Enums\CategoryType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
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
            if (Asset::withTrashed()->whereKey($id)->exists()) {
                throw new RowImportException("An asset with id [{$id}] already exists.");
            }
            $asset->{$asset->getKeyName()} = $id;
        }

        $asset->status_id = $this->resolveStatus($get('status'))->getKey();

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

            // The category belongs to the model: it is set only on a model that
            // has none yet, never changed, so one file can't reshuffle others.
            if ($model->category_id === null && ($category = $get('category')) !== '') {
                $model->forceFill(['category_id' => $this->resolveCategory($category)->getKey()])->save();
            }
        }

        if (($location = $get('location')) !== '') {
            $asset->location_id = $this->firstOrCreateByName(Location::class, $location)->getKey();
        }
        if (($user = $get('assigned_user')) !== '') {
            $asset->assignTo($this->resolveOwner($user));
        }
        if (($supplier = $get('supplier')) !== '') {
            $asset->supplier_id = $this->firstOrCreateByName(Supplier::class, $supplier)->getKey();
        }

        $asset->name = $get('name') ?: null;
        if (($tag = $get('asset_tag')) !== '') {
            if (Asset::query()->where('asset_tag', $tag)->exists()) {
                throw new RowImportException("An asset with tag [{$tag}] already exists.");
            }
            $asset->asset_tag = $tag;
        }
        $asset->serial = $get('serial') ?: null;
        $asset->purchase_date = $this->parseDate($get('purchase_date'));
        $asset->purchase_cost = $this->parseNumber($get('purchase_cost'), 'purchase cost');
        $asset->order_number = $get('order_number') ?: null;
        $months = $this->parseNumber($get('warranty_months'), 'warranty months');
        if ($months !== null && (string) (int) $months !== $get('warranty_months')) {
            throw new RowImportException("Invalid warranty months [{$get('warranty_months')}].");
        }
        $asset->warranty_months = $months === null ? null : (int) $months;
        $asset->notes = $get('notes') ?: null;

        $asset->save();

        $tags = collect(explode(',', $get('tags')))->map(fn ($t) => trim($t))->filter()->values()->all();
        if ($tags !== []) {
            $asset->syncTags($tags);
        }
    }

    private function parseNumber(string $value, string $label): ?string
    {
        if ($value === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw new RowImportException("Invalid {$label} [{$value}].");
        }

        return $value;
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
    /**
     * A status label by name, or by its former state value ("in-use"), so
     * files exported before status labels existed still import. Unknown
     * statuses are an error rather than created: a status's type matters.
     */
    private function resolveStatus(string $value): StatusLabel
    {
        if ($value === '') {
            throw new RowImportException('Missing required value.');
        }

        return StatusLabel::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->first()
            ?? StatusLabel::query()->where('system_key', $value)->first()
            ?? throw new RowImportException("Unknown status [{$value}].");
    }

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

    /**
     * Matches a live asset category by name, or creates one.
     */
    private function resolveCategory(string $name): Category
    {
        return Category::query()
            ->where('category_type', CategoryType::ASSET)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Category::query()->create(['name' => $name, 'category_type' => CategoryType::ASSET]);
    }

    private function resolveOwner(string $name): User
    {
        $user = User::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($user) {
            return $user;
        }
        $parts = preg_split('/\s+/', $name, 2);
        $firstname = $parts[0] ?? $name;
        $lastname = ($parts[1] ?? '') !== '' ? $parts[1] : '-';

        return User::query()->create([
            'name' => $name, 'firstname' => $firstname, 'lastname' => $lastname,
            'login_enabled' => false,
        ]);
    }
}
