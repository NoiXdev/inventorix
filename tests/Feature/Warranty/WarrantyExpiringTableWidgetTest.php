<?php

namespace Tests\Feature\Warranty;

use App\Filament\App\Widgets\WarrantyExpiringTableWidget;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarrantyExpiringTableWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_soonest_expiring_assets_first(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        $soonest = Asset::factory()->create(['guarantee_end' => now()->addDays(3)->toDateString()]);
        $later = Asset::factory()->create(['guarantee_end' => now()->addDays(40)->toDateString()]);
        $excluded = Asset::factory()->create(['guarantee_end' => null]);

        Livewire::test(WarrantyExpiringTableWidget::class)
            ->assertCanSeeTableRecords([$soonest, $later])
            ->assertCanNotSeeTableRecords([$excluded])
            ->assertCanRenderTableColumn('serial_number');
    }
}
