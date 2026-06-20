<?php

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Models\WarrantyNotification;
use App\Services\WarrantyScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyScannerTest extends TestCase
{
    use RefreshDatabase;

    private function scanner(): WarrantyScanner
    {
        return new WarrantyScanner([90, 30, 7]);
    }

    private function assetEndingInDays(int $days): Asset
    {
        return Asset::factory()->create([
            'guarantee_end' => now()->startOfDay()->addDays($days)->toDateString(),
        ]);
    }

    public function test_no_milestone_when_further_out_than_largest_lead(): void
    {
        $this->assetEndingInDays(91);

        $this->assertCount(0, $this->scanner()->scan());
    }

    public function test_picks_tightest_crossed_milestone(): void
    {
        $this->assetEndingInDays(90); // exactly 90 -> "90"
        $this->assetEndingInDays(50); // crossed 90 only -> "90"
        $this->assetEndingInDays(30); // -> "30"
        $this->assetEndingInDays(8);  // crossed 30 -> "30"
        $this->assetEndingInDays(7);  // -> "7"
        $this->assetEndingInDays(0);  // ends today -> "7"

        $milestones = $this->scanner()->scan()->pluck('milestone')->sort()->values()->all();

        sort($milestones);
        $this->assertSame(['7', '7', '30', '30', '90', '90'], $milestones);
    }

    public function test_expired_milestone_for_past_dates(): void
    {
        $this->assetEndingInDays(-1);

        $results = $this->scanner()->scan();

        $this->assertCount(1, $results);
        $this->assertSame('expired', $results->first()->milestone);
        $this->assertSame(-1, $results->first()->daysLeft);
    }

    public function test_null_guarantee_end_is_ignored(): void
    {
        Asset::factory()->create(['guarantee_end' => null]);

        $this->assertCount(0, $this->scanner()->scan());
    }

    public function test_already_recorded_triple_is_not_re_emitted(): void
    {
        $asset = $this->assetEndingInDays(30);
        WarrantyNotification::create([
            'asset_id' => $asset->id,
            'guarantee_end' => $asset->guarantee_end->toDateString(),
            'milestone' => '30',
            'sent_at' => now(),
        ]);

        $this->assertCount(0, $this->scanner()->scan());
    }

    public function test_changed_guarantee_end_is_a_fresh_slate(): void
    {
        $asset = $this->assetEndingInDays(30);
        // Ledger row for a DIFFERENT (older) guarantee_end must not suppress the new date.
        WarrantyNotification::create([
            'asset_id' => $asset->id,
            'guarantee_end' => now()->startOfDay()->subYear()->toDateString(),
            'milestone' => '30',
            'sent_at' => now(),
        ]);

        $this->assertCount(1, $this->scanner()->scan());
    }
}
