<?php

namespace Tests\Feature\AssetImportExport;

use App\Enums\AssetState;
use App\Filament\App\Resources\Assets\Exporters\AssetExporter;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_expected_columns(): void
    {
        $names = array_map(
            static fn ($column) => $column->getName(),
            AssetExporter::getColumns(),
        );

        $this->assertSame([
            'id',
            'state',
            'asset_type',
            'manufacturer',
            'model',
            'owner',
            'place',
            'serial_number',
            'buy_date',
            'guarantee_end',
            'buy_price',
            'buy_type',
            'tags',
        ], $names);
    }

    public function test_state_column_exports_the_german_label(): void
    {
        // ExportColumn has no record() setter; the record is held by the Exporter.
        // We test the formatting closure directly via formatState().
        $column = collect(AssetExporter::getColumns())
            ->firstWhere(fn ($c) => $c->getName() === 'state');

        $this->assertSame('In Benutzung', $column->formatState(AssetState::IN_USE));
    }

    public function test_list_page_renders_with_export_action(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['login_enabled' => true]));

        \Livewire\Livewire::test(\App\Filament\App\Resources\Assets\Pages\ListAssets::class)
            ->assertActionExists('export')
            ->assertSuccessful();
    }
}
