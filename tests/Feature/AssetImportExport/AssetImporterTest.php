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
            'serial_number' => 'SN-123',
        ]);

        $asset = Asset::query()->firstOrFail();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame('SN-123', $asset->serial_number);
    }

    public function test_it_accepts_state_by_german_label(): void
    {
        $this->import(['state' => 'In Benutzung']);

        $this->assertSame(AssetState::IN_USE, Asset::query()->firstOrFail()->state);
    }

    public function test_unknown_state_fails_the_row(): void
    {
        $this->expectException(RowImportFailedException::class);

        $this->import(['state' => 'banana']);
    }

    public function test_it_uses_a_supplied_id(): void
    {
        $this->import(['id' => 'ASSET-0001', 'state' => 'new']);

        $this->assertNotNull(Asset::query()->find('ASSET-0001'));
    }

    public function test_duplicate_id_fails_the_row(): void
    {
        Asset::factory()->create(['id' => 'ASSET-0001']);

        $this->expectException(RowImportFailedException::class);

        $this->import(['id' => 'ASSET-0001', 'state' => 'new']);
    }

    public function test_it_parses_dates(): void
    {
        $this->import([
            'state' => 'new',
            'buy_date' => '2025-01-15',
            'guarantee_end' => '15.01.2027',
        ]);

        $asset = Asset::query()->firstOrFail();
        $this->assertSame('2025-01-15', $asset->buy_date->toDateString());
        $this->assertSame('2027-01-15', $asset->guarantee_end->toDateString());
    }
}
