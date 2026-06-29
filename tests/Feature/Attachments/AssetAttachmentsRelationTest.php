<?php

namespace Tests\Feature\Attachments;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetAttachmentsRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_has_many_attachments_newest_first(): void
    {
        $asset = Asset::factory()->create();

        $older = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'created_at' => now()->subDay(),
        ]);
        $newer = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'created_at' => now(),
        ]);

        $ids = $asset->attachments()->pluck('id')->all();

        $this->assertCount(2, $ids);
        $this->assertSame($newer->getKey(), $ids[0]);
        $this->assertSame($older->getKey(), $ids[1]);
    }
}
