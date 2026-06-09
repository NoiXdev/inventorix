<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation\AssetValueReport;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetValueReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_aggregated_mode_sums_value_per_employee(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        Asset::factory()->create(['owner_id' => $alice->id, 'buy_price' => 100]);
        Asset::factory()->create(['owner_id' => $alice->id, 'buy_price' => 250]);

        $rows = Livewire::test(AssetValueReport::class)
            ->set('filters.group_by', 'employee')
            ->set('filters.detailed', false)
            ->instance()
            ->reportQuery()
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows->first()->assets_count);
        $this->assertSame(350.0, (float) $rows->first()->total_price);
    }

    public function test_detailed_mode_lists_each_asset(): void
    {
        $assets = Asset::factory()->count(3)->create();

        Livewire::test(AssetValueReport::class)
            ->set('filters.detailed', true)
            ->assertCanSeeTableRecords($assets);
    }

    public function test_pdf_and_exports_download_in_both_modes(): void
    {
        Asset::factory()->count(2)->create();

        Livewire::test(AssetValueReport::class)
            ->set('filters.detailed', false)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(AssetValueReport::class)
            ->set('filters.detailed', true)
            ->call('export', 'xlsx')->assertFileDownloaded();
    }
}
