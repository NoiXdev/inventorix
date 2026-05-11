<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssetActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_asset_logs_a_created_activity(): void
    {
        $asset = Asset::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->where('description', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('asset', $activity->log_name);
    }

    public function test_updating_non_semantic_fields_writes_only_dirty_fields(): void
    {
        $asset = Asset::factory()->create(['serial_number' => 'SN-OLD']);

        $asset->update(['serial_number' => 'SN-NEW', 'invoice' => 'INV-99']);

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->latest('id')->first();

        $this->assertSame('SN-NEW', $activity->attribute_changes['attributes']['serial_number']);
        $this->assertSame('SN-OLD', $activity->attribute_changes['old']['serial_number']);
        $this->assertArrayHasKey('invoice', $activity->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('id', $activity->attribute_changes['attributes']);
    }

    public function test_deleting_writes_a_deleted_activity(): void
    {
        $asset = Asset::factory()->create();
        $id = $asset->id;

        $asset->delete();

        $this->assertTrue(
            Activity::query()
                ->where('subject_id', $id)
                ->where('description', 'deleted')
                ->exists(),
        );
    }

    public function test_authenticated_user_is_recorded_as_causer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'created')
            ->first();

        $this->assertSame((string) $user->id, (string) $activity->causer_id);
    }
}
