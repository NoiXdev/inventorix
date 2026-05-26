<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class IncidentActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_incident_logs_a_created_activity(): void
    {
        $incident = Incident::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Incident::class)
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'created')
            ->first();

        $this->assertNotNull($activity, 'expected a created activity row');
        $this->assertSame('incident', $activity->log_name);
    }

    public function test_updating_logged_fields_writes_an_updated_activity_with_dirty_only(): void
    {
        $incident = Incident::factory()->create(['title' => 'Old']);

        $incident->update(['title' => 'New', 'notes' => 'fresh']);

        $activity = Activity::query()
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'updated')
            ->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('New', $activity->attribute_changes['attributes']['title']);
        $this->assertSame('Old', $activity->attribute_changes['old']['title']);
        $this->assertArrayHasKey('notes', $activity->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('id', $activity->attribute_changes['attributes']);
    }

    public function test_deleting_writes_a_deleted_activity(): void
    {
        $incident = Incident::factory()->create();
        $id = $incident->id;

        $incident->delete();

        $this->assertTrue(
            Activity::query()
                ->where('subject_id', (string) $id)
                ->where('description', 'deleted')
                ->exists(),
            'expected a deleted activity row',
        );
    }

    public function test_authenticated_user_is_recorded_as_causer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $incident = Incident::factory()->create();

        $activity = Activity::query()
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'created')
            ->first();

        $this->assertSame((string) $user->id, (string) $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
    }

    public function test_cli_changes_leave_causer_null(): void
    {
        $incident = Incident::factory()->create();
        $this->assertNull(
            Activity::query()
                ->where('subject_id', (string) $incident->id)
                ->where('description', 'created')
                ->first()
                ->causer_id,
        );
    }
}
