<?php

namespace Tests\Feature\AssetImportExport;

use App\Enums\AssetState;
use App\Filament\App\Resources\Assets\Importers\AssetImporter;
use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run a single row through the importer, exactly as the queued job would.
     *
     * @param  array<string, mixed>  $row
     */
    private function import(array $row): void
    {
        $import = new Import();
        $import->user_id = User::factory()->create()->getKey();
        $import->file_name = 'assets.csv';
        $import->file_path = 'assets.csv';
        $import->importer = AssetImporter::class;
        $import->total_rows = 1;
        $import->save();

        $columnMap = [];
        foreach (AssetImporter::getColumns() as $column) {
            $columnMap[$column->getName()] = $column->getName();
        }

        $importer = app(AssetImporter::class, [
            'import' => $import,
            'columnMap' => $columnMap,
            'options' => [],
        ]);

        $importer($row);
    }

    public function test_it_imports_a_minimal_asset_with_state_by_value(): void
    {
        $this->import([
            'state' => 'in-use',
            'asset_type' => 'Laptop',
            'serial_number' => 'SN-123',
        ]);

        $asset = Asset::query()->firstOrFail();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame('SN-123', $asset->serial_number);
    }

    public function test_it_accepts_state_by_german_label(): void
    {
        $this->import(['state' => 'In Benutzung', 'asset_type' => 'Laptop']);

        $this->assertSame(AssetState::IN_USE, Asset::query()->firstOrFail()->state);
    }

    public function test_unknown_state_fails_the_row(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->import(['state' => 'banana', 'asset_type' => 'Laptop']);
    }

    public function test_it_uses_a_supplied_id(): void
    {
        $this->import(['id' => 'ASSET-0001', 'state' => 'new', 'asset_type' => 'Laptop']);

        $this->assertNotNull(Asset::query()->find('ASSET-0001'));
    }

    public function test_duplicate_id_fails_the_row(): void
    {
        Asset::factory()->create(['id' => 'ASSET-0001']);

        $this->expectException(RowImportFailedException::class);

        $this->import(['id' => 'ASSET-0001', 'state' => 'new', 'asset_type' => 'Laptop']);
    }

    public function test_it_parses_dates(): void
    {
        $this->import([
            'state' => 'new',
            'asset_type' => 'Laptop',
            'buy_date' => '2025-01-15',
            'guarantee_end' => '15.01.2027',
        ]);

        $asset = Asset::query()->firstOrFail();
        $this->assertSame('2025-01-15', $asset->buy_date->toDateString());
        $this->assertSame('2027-01-15', $asset->guarantee_end->toDateString());
    }

    public function test_it_creates_and_reuses_the_asset_type_case_insensitively(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop']);
        $this->import(['state' => 'new', 'asset_type' => '  laptop ']);

        $this->assertSame(1, \App\Models\AssetType::query()->count());
        $this->assertSame('Laptop', \App\Models\Asset::query()->firstOrFail()->assetType->name);
    }

    public function test_it_imports_buy_type_by_backed_value(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'buy_type' => 'once']);

        $this->assertSame(\App\Enums\BuyType::ONCE, \App\Models\Asset::query()->firstOrFail()->buy_type);
    }

    public function test_it_passes_through_buy_price(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'buy_price' => '1299.99']);

        $this->assertEquals(1299.99, \App\Models\Asset::query()->firstOrFail()->buy_price);
    }

    public function test_invalid_date_fails_the_row(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'buy_date' => 'not-a-date']);
    }

    public function test_it_auto_creates_manufacturer_model_and_place(): void
    {
        $this->import([
            'state' => 'new',
            'asset_type' => 'Laptop',
            'manufacturer' => 'Dell',
            'model' => 'Latitude 7440',
            'place' => 'Büro 1',
        ]);

        $asset = \App\Models\Asset::query()->firstOrFail();
        $this->assertSame('Latitude 7440', $asset->model->name);
        $this->assertSame('Dell', $asset->model->manufacturer->name);
        $this->assertSame('Büro 1', $asset->place->name);
    }

    public function test_it_matches_an_existing_manufacturer_case_insensitively(): void
    {
        $manufacturer = \App\Models\Manufacturer::factory()->create(['name' => 'Dell']);

        $this->import([
            'state' => 'new',
            'asset_type' => 'Laptop',
            'manufacturer' => '  dell ',
            'model' => 'Latitude 7440',
        ]);

        $this->assertSame(1, \App\Models\Manufacturer::query()->count());
        $this->assertSame($manufacturer->getKey(), \App\Models\Asset::query()->firstOrFail()->model->manufacturer_id);
    }

    public function test_model_without_manufacturer_fails_the_row(): void
    {
        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);

        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'model' => 'Latitude 7440']);
    }

    public function test_same_model_name_under_different_manufacturers_creates_two_records(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'Latitude 7440']);
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'HP', 'model' => 'Latitude 7440']);

        $this->assertSame(2, \App\Models\AssetModel::query()->count());
        $this->assertSame(2, \App\Models\Asset::query()->count());
    }

    public function test_it_reuses_an_existing_model_case_insensitively_within_a_manufacturer(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'Latitude 7440']);
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => '  latitude 7440 ']);

        $this->assertSame(1, \App\Models\AssetModel::query()->count());
        $this->assertSame(2, \App\Models\Asset::query()->count());
    }
}
