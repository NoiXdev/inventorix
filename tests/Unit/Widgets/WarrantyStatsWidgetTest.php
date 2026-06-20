<?php

namespace Tests\Unit\Widgets;

use App\Filament\App\Widgets\WarrantyStatsWidget;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_are_bucketed_correctly(): void
    {
        Asset::factory()->create(['guarantee_end' => now()->subDay()->toDateString()]);   // expired
        Asset::factory()->create(['guarantee_end' => now()->addDays(10)->toDateString()]); // <=30 and <=90
        Asset::factory()->create(['guarantee_end' => now()->addDays(60)->toDateString()]); // <=90 only
        Asset::factory()->create(['guarantee_end' => now()->addDays(200)->toDateString()]); // neither
        Asset::factory()->create(['guarantee_end' => null]);                                // ignored

        $counts = (new WarrantyStatsWidget)->counts();

        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['soon_30']); // cumulative: today..+30
        $this->assertSame(2, $counts['soon_90']); // cumulative: today..+90
    }
}
