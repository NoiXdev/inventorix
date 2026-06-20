<?php

namespace Tests\Unit\Models;

use App\Models\Asset;
use App\Models\WarrantyNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_index_blocks_duplicate_triple(): void
    {
        $asset = Asset::factory()->create();

        WarrantyNotification::create([
            'asset_id' => $asset->id,
            'guarantee_end' => '2026-09-01',
            'milestone' => '90',
            'sent_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        WarrantyNotification::create([
            'asset_id' => $asset->id,
            'guarantee_end' => '2026-09-01',
            'milestone' => '90',
            'sent_at' => now(),
        ]);
    }

    public function test_same_asset_different_guarantee_end_is_allowed(): void
    {
        $asset = Asset::factory()->create();

        WarrantyNotification::create(['asset_id' => $asset->id, 'guarantee_end' => '2026-09-01', 'milestone' => '90', 'sent_at' => now()]);
        WarrantyNotification::create(['asset_id' => $asset->id, 'guarantee_end' => '2027-09-01', 'milestone' => '90', 'sent_at' => now()]);

        $this->assertSame(2, WarrantyNotification::count());
    }
}
