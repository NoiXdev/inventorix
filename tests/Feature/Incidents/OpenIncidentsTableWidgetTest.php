<?php

namespace Tests\Feature\Incidents;

use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Widgets\OpenIncidentsTableWidget;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpenIncidentsTableWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_open_incidents_newest_first(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        $newest = Incident::factory()->create(['open_date' => now(), 'closed_date' => null]);
        $older = Incident::factory()->create(['open_date' => now()->subWeek(), 'closed_date' => null]);
        $closed = Incident::factory()->create(['open_date' => now()->subDay(), 'closed_date' => now()]);

        Livewire::test(OpenIncidentsTableWidget::class)
            ->assertCanSeeTableRecords([$newest, $older])
            ->assertCanNotSeeTableRecords([$closed])
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('asset.model.name')
            ->assertCanRenderTableColumn('asset.serial_number')
            ->assertCanRenderTableColumn('days_open')
            ->assertTableActionExists('open');
    }

    public function test_the_open_action_links_to_the_asset_edit_page(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        $incident = Incident::factory()->create(['closed_date' => null]);

        Livewire::test(OpenIncidentsTableWidget::class)
            ->assertTableActionHasUrl(
                'open',
                AssetResource::getUrl('edit', ['record' => $incident->asset_id]),
                record: $incident,
            );
    }
}
