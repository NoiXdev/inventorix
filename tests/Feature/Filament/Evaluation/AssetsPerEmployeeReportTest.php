<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation;
use App\Filament\App\Pages\Evaluation\AssetsPerEmployeeReport;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetsPerEmployeeReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_filtering_by_employee_shows_only_their_assets(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceAsset = Asset::factory()->create(['owner_id' => $alice->id]);
        $bobAsset = Asset::factory()->create(['owner_id' => $bob->id]);

        Livewire::test(AssetsPerEmployeeReport::class)
            ->set('filters.employees', [$alice->id])
            ->assertCanSeeTableRecords([$aliceAsset])
            ->assertCanNotSeeTableRecords([$bobAsset]);
    }

    public function test_without_filter_all_assets_are_listed(): void
    {
        $assets = Asset::factory()->count(3)->create();

        Livewire::test(AssetsPerEmployeeReport::class)
            ->assertCanSeeTableRecords($assets);
    }

    public function test_pdf_action_downloads_a_file(): void
    {
        Asset::factory()->create();

        Livewire::test(AssetsPerEmployeeReport::class)
            ->call('downloadPdf')
            ->assertFileDownloaded();
    }

    public function test_csv_export_downloads_a_file(): void
    {
        Asset::factory()->create();

        Livewire::test(AssetsPerEmployeeReport::class)
            ->call('export', 'csv')
            ->assertFileDownloaded();
    }

    public function test_report_metadata_is_defined(): void
    {
        $this->assertSame('assets_per_employee', AssetsPerEmployeeReport::reportKey());
        $this->assertNotEmpty(AssetsPerEmployeeReport::reportLabel());
        $this->assertNotEmpty(AssetsPerEmployeeReport::reportDescription());
    }

    public function test_evaluation_index_lists_the_report_card(): void
    {
        Livewire::test(Evaluation::class)
            ->assertSee(AssetsPerEmployeeReport::reportLabel());
    }
}
