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

    public function test_aggregated_mode_groups_by_state(): void
    {
        Asset::factory()->create(['state' => \App\Enums\AssetState::IN_USE->value, 'buy_price' => 100]);
        Asset::factory()->create(['state' => \App\Enums\AssetState::IN_USE->value, 'buy_price' => 200]);
        Asset::factory()->create(['state' => \App\Enums\AssetState::DEFECT->value, 'buy_price' => 50]);

        $rows = Livewire::test(AssetValueReport::class)
            ->set('filters.group_by', 'state')
            ->set('filters.detailed', false)
            ->instance()
            ->reportQuery()
            ->get()
            ->keyBy('state');

        $this->assertSame(300.0, (float) $rows[\App\Enums\AssetState::IN_USE->value]->total_price);
        $this->assertSame(1, (int) $rows[\App\Enums\AssetState::DEFECT->value]->assets_count);
    }

    public function test_aggregated_mode_groups_by_asset_type(): void
    {
        $type = \App\Models\AssetType::factory()->create();
        Asset::factory()->count(2)->create(['asset_type_id' => $type->id, 'buy_price' => 100]);

        $rows = Livewire::test(AssetValueReport::class)
            ->set('filters.group_by', 'asset_type')
            ->set('filters.detailed', false)
            ->instance()
            ->reportQuery()
            ->get()
            ->keyBy('asset_type_id');

        $this->assertSame(2, (int) $rows[$type->id]->assets_count);
        $this->assertSame(200.0, (float) $rows[$type->id]->total_price);
    }

    public function test_aggregated_employee_mode_renders_with_unowned_assets(): void
    {
        Asset::factory()->create(['owner_id' => null, 'buy_price' => 100]);
        Asset::factory()->create(['owner_id' => User::factory(), 'buy_price' => 50]);

        Livewire::test(AssetValueReport::class)
            ->set('filters.group_by', 'employee')
            ->set('filters.detailed', false)
            ->assertOk();
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
