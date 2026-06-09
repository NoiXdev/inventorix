<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Enums\AssetState;
use App\Filament\App\Pages\Evaluation\StateOverviewReport;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StateOverviewReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_counts_and_sums_per_state(): void
    {
        Asset::factory()->create(['state' => AssetState::IN_USE->value, 'buy_price' => 100]);
        Asset::factory()->create(['state' => AssetState::IN_USE->value, 'buy_price' => 200]);
        Asset::factory()->create(['state' => AssetState::DEFECT->value, 'buy_price' => 50]);

        $rows = Livewire::test(StateOverviewReport::class)
            ->instance()
            ->reportQuery()
            ->get()
            ->keyBy('state');

        $this->assertSame(2, (int) $rows[AssetState::IN_USE->value]->assets_count);
        $this->assertSame(300.0, (float) $rows[AssetState::IN_USE->value]->total_price);
        $this->assertSame(1, (int) $rows[AssetState::DEFECT->value]->assets_count);
    }

    public function test_pdf_and_csv_download(): void
    {
        Asset::factory()->count(2)->create();

        Livewire::test(StateOverviewReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(StateOverviewReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
