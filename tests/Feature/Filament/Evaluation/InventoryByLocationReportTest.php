<?php

namespace Tests\Feature\Filament\Evaluation;

use App\Filament\App\Pages\Evaluation\InventoryByLocationReport;
use App\Models\Asset;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryByLocationReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_filtering_by_place_shows_only_assets_there(): void
    {
        $office = Place::factory()->create();
        $warehouse = Place::factory()->create();
        $officeAsset = Asset::factory()->create(['place_id' => $office->id]);
        $warehouseAsset = Asset::factory()->create(['place_id' => $warehouse->id]);

        Livewire::test(InventoryByLocationReport::class)
            ->set('filters.places', [$office->id])
            ->assertCanSeeTableRecords([$officeAsset])
            ->assertCanNotSeeTableRecords([$warehouseAsset]);
    }

    public function test_pdf_and_csv_download(): void
    {
        Asset::factory()->create();

        Livewire::test(InventoryByLocationReport::class)
            ->call('downloadPdf')->assertFileDownloaded();
        Livewire::test(InventoryByLocationReport::class)
            ->call('export', 'csv')->assertFileDownloaded();
    }
}
