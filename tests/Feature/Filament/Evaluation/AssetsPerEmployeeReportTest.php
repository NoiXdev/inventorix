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

    public function test_xlsx_export_downloads_a_file(): void
    {
        Asset::factory()->create();

        Livewire::test(AssetsPerEmployeeReport::class)
            ->call('export', 'xlsx')
            ->assertFileDownloaded();
    }

    public function test_pdf_groups_each_employee_with_a_page_break_between_them(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        Asset::factory()->count(2)->create(['owner_id' => $alice->id]);
        Asset::factory()->create(['owner_id' => $bob->id]);

        $page = Livewire::test(AssetsPerEmployeeReport::class)
            ->set('filters.employees', [$alice->id, $bob->id]);

        $data = $page->instance()->pdfData();

        // One group per employee, ordered by name.
        $this->assertCount(2, $data['groups']);
        $this->assertSame('Alice', $data['groups'][0]['employee']);
        $this->assertSame('Bob', $data['groups'][1]['employee']);
        $this->assertCount(2, $data['groups'][0]['rows']);
        $this->assertCount(1, $data['groups'][1]['rows']);

        // The rendered PDF view has exactly one page break for two employees (= two pages).
        $html = view($page->instance()->pdfView(), $data)->render();
        $this->assertSame(1, substr_count($html, 'page-break-before'));
    }

    public function test_pdf_puts_assets_without_owner_in_a_trailing_group(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        Asset::factory()->create(['owner_id' => $alice->id]);
        Asset::factory()->create(['owner_id' => null]);

        $page = Livewire::test(AssetsPerEmployeeReport::class);
        $data = $page->instance()->pdfData();

        $this->assertCount(2, $data['groups']);
        $this->assertSame('Alice', $data['groups'][0]['employee']);
        $this->assertFalse($data['groups'][1]['hasOwner']);
        $this->assertSame(
            __('evaluation.reports.assets_per_employee.pdf.no_owner'),
            $data['groups'][1]['employee'],
        );
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
