<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
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
        $asset = Asset::factory()->create(['serial' => 'SN-OLD']);

        $asset->update(['serial' => 'SN-NEW', 'order_number' => 'PO-99']);

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->latest('id')->first();

        $this->assertSame('SN-NEW', $activity->attribute_changes['attributes']['serial']);
        $this->assertSame('SN-OLD', $activity->attribute_changes['old']['serial']);
        $this->assertArrayHasKey('order_number', $activity->attribute_changes['attributes']);
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

    public function test_assignee_change_writes_an_assignee_changed_activity(): void
    {
        $asset = Asset::factory()->create();
        $newOwner = User::factory()->withoutLogin()->create();
        $oldOwnerId = $asset->assignee_id;
        $location = Location::factory()->create();

        $asset->assignTo($newOwner)->save();
        $asset->assignTo($location)->save();

        $activities = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'assignee_changed')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $activities);
        $this->assertSame(['type' => User::class, 'id' => $oldOwnerId], $activities[0]->properties['from']);
        $this->assertSame(['type' => User::class, 'id' => $newOwner->id], $activities[0]->properties['to']);
        $this->assertSame(['type' => Location::class, 'id' => $location->id], $activities[1]->properties['to']);
    }

    public function test_location_change_writes_a_location_changed_activity(): void
    {
        $asset = Asset::factory()->create();
        $newPlace = Location::factory()->create();
        $oldPlaceId = $asset->location_id;

        $asset->update(['location_id' => $newPlace->id]);

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'location_changed')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($oldPlaceId, $activity->properties['from']);
        $this->assertSame($newPlace->id, $activity->properties['to']);
    }

    public function test_status_change_writes_a_status_changed_activity(): void
    {
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id]);

        $asset->update(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'status_changed')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(StatusLabel::system(SystemStatus::NEW)->id, $activity->properties['from']);
        $this->assertSame(StatusLabel::system(SystemStatus::IN_USE)->id, $activity->properties['to']);
    }

    public function test_a_semantic_change_also_keeps_the_generic_updated_row(): void
    {
        $asset = Asset::factory()->create();
        $newOwner = User::factory()->withoutLogin()->create();

        $asset->assignTo($newOwner)->save();

        $this->assertSame(
            1,
            Activity::query()
                ->where('subject_id', $asset->id)
                ->where('description', 'updated')
                ->count(),
            'generic updated row should still be written',
        );
        $this->assertSame(
            1,
            Activity::query()
                ->where('subject_id', $asset->id)
                ->where('description', 'assignee_changed')
                ->count(),
        );
    }
}
