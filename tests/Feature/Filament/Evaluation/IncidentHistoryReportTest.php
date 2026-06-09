<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation\IncidentHistoryReport;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_status_filter_shows_only_open_incidents(): void
    {
        $open = Incident::factory()->create(['closed_date' => null]);
        $closed = Incident::factory()->create(['closed_date' => now()]);

        Livewire::test(IncidentHistoryReport::class)
            ->set('filters.status', 'open')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_pdf_and_csv_download(): void
    {
        Incident::factory()->create();

        Livewire::test(IncidentHistoryReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(IncidentHistoryReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
