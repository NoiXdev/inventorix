<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation\AssetAgingReport;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetAgingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_only_assets_older_than_min_age_are_listed(): void
    {
        $old = Asset::factory()->create(['buy_date' => now()->subYears(5)->format('Y-m-d')]);
        $new = Asset::factory()->create(['buy_date' => now()->subMonths(6)->format('Y-m-d')]);

        Livewire::test(AssetAgingReport::class)
            ->set('filters.min_age_years', 3)
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$new]);
    }

    public function test_pdf_and_csv_download(): void
    {
        Asset::factory()->create(['buy_date' => now()->subYears(5)->format('Y-m-d')]);

        Livewire::test(AssetAgingReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(AssetAgingReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
