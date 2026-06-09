<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation\GuaranteeStatusReport;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuaranteeStatusReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_status_filter_shows_only_expired_assets(): void
    {
        $expired = Asset::factory()->create(['guarantee_end' => now()->subDays(5)->format('Y-m-d')]);
        $valid = Asset::factory()->create(['guarantee_end' => now()->addYears(1)->format('Y-m-d')]);

        Livewire::test(GuaranteeStatusReport::class)
            ->set('filters.status', ['expired'])
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$valid]);
    }

    public function test_expiring_soon_uses_a_90_day_window(): void
    {
        $soon = Asset::factory()->create(['guarantee_end' => now()->addDays(30)->format('Y-m-d')]);
        $later = Asset::factory()->create(['guarantee_end' => now()->addDays(200)->format('Y-m-d')]);

        Livewire::test(GuaranteeStatusReport::class)
            ->set('filters.status', ['expiring_soon'])
            ->assertCanSeeTableRecords([$soon])
            ->assertCanNotSeeTableRecords([$later]);
    }

    public function test_90_day_boundary_separates_expiring_soon_from_valid(): void
    {
        $onBoundary = Asset::factory()->create(['guarantee_end' => now()->addDays(90)->format('Y-m-d')]);
        $justOutside = Asset::factory()->create(['guarantee_end' => now()->addDays(91)->format('Y-m-d')]);

        Livewire::test(GuaranteeStatusReport::class)
            ->set('filters.status', ['expiring_soon'])
            ->assertCanSeeTableRecords([$onBoundary])
            ->assertCanNotSeeTableRecords([$justOutside]);

        Livewire::test(GuaranteeStatusReport::class)
            ->set('filters.status', ['valid'])
            ->assertCanSeeTableRecords([$justOutside])
            ->assertCanNotSeeTableRecords([$onBoundary]);
    }

    public function test_none_status_shows_only_assets_without_a_guarantee(): void
    {
        $withGuarantee = Asset::factory()->create(['guarantee_end' => now()->addYears(1)->format('Y-m-d')]);
        $withoutGuarantee = Asset::factory()->create(['guarantee_end' => null]);

        Livewire::test(GuaranteeStatusReport::class)
            ->set('filters.status', ['none'])
            ->assertCanSeeTableRecords([$withoutGuarantee])
            ->assertCanNotSeeTableRecords([$withGuarantee]);
    }

    public function test_pdf_and_csv_download(): void
    {
        Asset::factory()->create();

        Livewire::test(GuaranteeStatusReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(GuaranteeStatusReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
