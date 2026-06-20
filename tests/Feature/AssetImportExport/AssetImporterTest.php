<?php

namespace Tests\Feature\AssetImportExport;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Filament\App\Resources\Assets\Importers\AssetImporter;
use App\Filament\App\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $import = new Import;
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

        $this->assertSame(1, AssetType::query()->count());
        $this->assertSame('Laptop', Asset::query()->firstOrFail()->assetType->name);
    }

    public function test_it_imports_buy_type_by_backed_value(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'buy_type' => 'once']);

        $this->assertSame(BuyType::ONCE, Asset::query()->firstOrFail()->buy_type);
    }

    public function test_it_passes_through_buy_price(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'buy_price' => '1299.99']);

        $this->assertEquals(1299.99, Asset::query()->firstOrFail()->buy_price);
    }

    public function test_invalid_date_fails_the_row(): void
    {
        $this->expectException(RowImportFailedException::class);

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

        $asset = Asset::query()->firstOrFail();
        $this->assertSame('Latitude 7440', $asset->model->name);
        $this->assertSame('Dell', $asset->model->manufacturer->name);
        $this->assertSame('Büro 1', $asset->place->name);
    }

    public function test_it_matches_an_existing_manufacturer_case_insensitively(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Dell']);

        $this->import([
            'state' => 'new',
            'asset_type' => 'Laptop',
            'manufacturer' => '  dell ',
            'model' => 'Latitude 7440',
        ]);

        $this->assertSame(1, Manufacturer::query()->count());
        $this->assertSame($manufacturer->getKey(), Asset::query()->firstOrFail()->model->manufacturer_id);
    }

    public function test_model_without_manufacturer_fails_the_row(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'model' => 'Latitude 7440']);
    }

    public function test_same_model_name_under_different_manufacturers_creates_two_records(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'Latitude 7440']);
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'HP', 'model' => 'Latitude 7440']);

        $this->assertSame(2, AssetModel::query()->count());
        $this->assertSame(2, Asset::query()->count());
    }

    public function test_it_reuses_an_existing_model_case_insensitively_within_a_manufacturer(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'Latitude 7440']);
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'manufacturer' => 'Dell', 'model' => '  latitude 7440 ']);

        $this->assertSame(1, AssetModel::query()->count());
        $this->assertSame(2, Asset::query()->count());
    }

    public function test_it_matches_an_existing_owner_by_name(): void
    {
        $owner = User::factory()->create(['name' => 'Max Mustermann']);

        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'owner' => 'Max Mustermann']);

        $this->assertSame($owner->getKey(), Asset::query()->firstOrFail()->owner_id);
        $this->assertSame(1, User::query()->where('name', 'Max Mustermann')->count());
    }

    public function test_it_creates_a_non_login_owner_when_missing(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'owner' => 'Erika Musterfrau']);

        $owner = Asset::query()->firstOrFail()->owner;
        $this->assertSame('Erika Musterfrau', $owner->name);
        $this->assertSame('Erika', $owner->firstname);
        $this->assertSame('Musterfrau', $owner->lastname);
        $this->assertFalse($owner->login_enabled);
        $this->assertNull($owner->email);
    }

    public function test_it_creates_a_single_name_owner_with_placeholder_lastname(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'owner' => 'Cher']);

        $owner = Asset::query()->firstOrFail()->owner;
        $this->assertSame('Cher', $owner->firstname);
        $this->assertSame('-', $owner->lastname);
    }

    public function test_it_matches_an_existing_owner_case_insensitively(): void
    {
        $owner = User::factory()->create(['name' => 'Max Mustermann']);

        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'owner' => 'MAX MUSTERMANN']);

        $this->assertSame($owner->getKey(), Asset::query()->firstOrFail()->owner_id);
        $this->assertSame(1, User::query()->whereRaw('LOWER(name) = ?', ['max mustermann'])->count());
    }

    public function test_it_syncs_comma_separated_tags(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'tags' => 'mobil, leasing , vip']);

        $asset = Asset::query()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['mobil', 'leasing', 'vip'],
            $asset->tags->pluck('name')->all(),
        );
    }

    public function test_blank_tags_create_no_tags(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'tags' => '']);

        $this->assertCount(0, Asset::query()->firstOrFail()->tags);
    }

    public function test_comma_only_tags_string_creates_no_tags(): void
    {
        $this->import(['state' => 'new', 'asset_type' => 'Laptop', 'tags' => ',,  ,']);

        $this->assertCount(0, Asset::query()->firstOrFail()->tags);
    }

    public function test_list_page_renders_with_import_action(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        Livewire::test(ListAssets::class)
            ->assertActionExists('import')
            ->assertSuccessful();
    }
}
