<?php

// tests/Feature/App/AssetHistoryTest.php

namespace Tests\Feature\App;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\StatusLabel;
use App\Models\User;
use App\Support\Assets\AssetHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssetHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_owner_change_to_names_and_records_causer(): void
    {
        $actor = User::factory()->superuser()->create(['name' => 'Cara Causer', 'login_enabled' => true]);
        $oldOwner = User::factory()->withoutLogin()->create(['name' => 'Olive Old']);
        $newOwner = User::factory()->withoutLogin()->create(['name' => 'Nate New']);

        $this->actingAs($actor);
        $asset = Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => $oldOwner->id, 'status_id' => StatusLabel::system(SystemStatus::NEW)->id]);
        $asset->assignTo($newOwner)->save();

        $history = AssetHistory::for($asset->fresh());

        // newest first: the update entry is first
        $updated = $history[0];
        $this->assertSame('updated', $updated['event']);
        $this->assertSame('Cara Causer', $updated['causer_name']);
        $ownerChange = collect($updated['changes'])->firstWhere('field', 'assignee_id');
        $this->assertNotNull($ownerChange);
        $this->assertSame('Olive Old', $ownerChange['old']);
        $this->assertSame('Nate New', $ownerChange['new']);
    }

    public function test_resolves_status_label_names(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id]);
        $asset->update(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);

        $stateChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'status_id');
        $this->assertSame(StatusLabel::system(SystemStatus::NEW)->name, $stateChange['old']);   // 'Neu'
        $this->assertSame(StatusLabel::system(SystemStatus::DEFECT)->name, $stateChange['new']); // 'Defekt'
    }

    public function test_entries_from_before_categories_resolve_the_old_asset_type_id(): void
    {
        $this->actingAs(User::factory()->superuser()->create(['login_enabled' => true]));
        $category = Category::factory()->create(['name' => 'Monitor']);
        $asset = Asset::factory()->create();
        // Types were migrated to categories with their ids kept.
        $activity = Activity::query()->where('subject_id', $asset->id)->sole();
        $activity->attribute_changes = collect(['attributes' => ['asset_type_id' => $category->id], 'old' => []]);
        $activity->save();

        $change = collect(AssetHistory::for($asset)[0]['changes'])->firstWhere('field', 'asset_type_id');
        $this->assertSame('Category', $change['label']);
        $this->assertSame('Monitor', $change['new']);
    }

    public function test_missing_referenced_record_resolves_to_removed(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $gone = User::factory()->withoutLogin()->create();
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['assignee_type' => null, 'assignee_id' => null]);
        $asset->assignTo($gone)->save();
        $gone->delete();

        $ownerChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'assignee_id');
        $this->assertSame('(removed)', $ownerChange['new']);
    }

    public function test_null_causer_shows_system(): void
    {
        // No actingAs -> no auth user -> null causer.
        $asset = Asset::factory()->create();
        $asset->update(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);

        $entry = AssetHistory::for($asset->fresh())[0];
        $this->assertSame('System', $entry['causer_name']);
    }

    public function test_excludes_semantic_observer_entries(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $newOwner = User::factory()->withoutLogin()->create();

        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $asset->assignTo($newOwner)->save();

        $history = AssetHistory::for($asset->fresh());

        // Without the event-scoping filter, the AssetObserver's null-event
        // 'owner_changed' row would appear as a third entry here.
        $this->assertCount(2, $history);

        foreach ($history as $entry) {
            $this->assertNotNull($entry['event']);
            $this->assertContains($entry['event'], ['created', 'updated', 'deleted']);
        }

        $updatedEntries = collect($history)->where('event', 'updated');
        $this->assertCount(1, $updatedEntries);
        $ownerChange = collect($updatedEntries->first()['changes'])->firstWhere('field', 'assignee_id');
        $this->assertNotNull($ownerChange);
        $this->assertSame($newOwner->name, $ownerChange['new']);
    }

    public function test_former_user_causer(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $second = User::factory()->superuser()->create(['login_enabled' => true]);

        $this->actingAs($actor);
        $asset = Asset::factory()->create();

        $this->actingAs($second);
        $asset->update(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);

        $second->delete();

        $entry = AssetHistory::for($asset->fresh())[0];
        $this->assertSame('Former user', $entry['causer_name']);
    }

    public function test_excludes_attachment_activity_rows(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();

        // Fires AttachmentObserver::created() -> logs 'attachment_added'
        // against the asset as subject, with a non-null event.
        $asset->attachments()->create([
            'path' => 'attachments/test-file.pdf',
            'original_name' => 'test-file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'type' => 'document',
        ]);

        $history = AssetHistory::for($asset->fresh());

        foreach ($history as $entry) {
            $this->assertNotNull($entry['event']);
            $this->assertContains($entry['event'], ['created', 'updated', 'deleted']);
        }

        $this->assertNull(collect($history)->firstWhere('event', 'attachment_added'));
        $this->assertNull(collect($history)->firstWhere('event', 'attachment_removed'));
    }

    public function test_order_number_change_shows_order_number_label(): void
    {
        $this->actingAs(User::factory()->superuser()->create(['login_enabled' => true]));
        $asset = Asset::factory()->create(['order_number' => 'PO-OLD']);
        $asset->update(['order_number' => 'PO-NEW']);

        $change = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'order_number');
        $this->assertNotNull($change);
        $this->assertSame('Order number', $change['label']);
        $this->assertSame('PO-OLD', $change['old']);
        $this->assertSame('PO-NEW', $change['new']);
    }

    public function test_show_returns_history_prop(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $asset->update(['status_id' => StatusLabel::system(SystemStatus::LEND)->id]);

        $this->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('assets/show')
                ->has('history', fn ($h) => $h->has('0.event')->has('0.causer_name')->has('0.changes')->etc())
                ->etc());
    }
}
