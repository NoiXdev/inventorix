<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Enums\HandoverType;
use App\Filament\App\Pages\Evaluation\HandoverHistoryReport;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_type_filter_shows_only_matching_handovers(): void
    {
        $issue = Handover::factory()->create(['type' => HandoverType::ISSUE->value]);
        $lend = Handover::factory()->create(['type' => HandoverType::LEND->value]);

        Livewire::test(HandoverHistoryReport::class)
            ->set('filters.type', [HandoverType::ISSUE->value])
            ->assertCanSeeTableRecords([$issue])
            ->assertCanNotSeeTableRecords([$lend]);
    }

    public function test_pdf_and_csv_download(): void
    {
        Handover::factory()->create();

        Livewire::test(HandoverHistoryReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(HandoverHistoryReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
