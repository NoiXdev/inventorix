<?php

// tests/Feature/App/AssetHistoryTest.php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use App\Support\Assets\AssetHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssetHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_owner_change_to_names_and_records_causer(): void
    {
        $causerPerson = Person::factory()->create(['name' => 'Cara Causer']);
        $actor = User::factory()->create(['person_id' => $causerPerson->id, 'login_enabled' => true]);
        $oldOwner = Person::factory()->create(['name' => 'Olive Old']);
        $newOwner = Person::factory()->create(['name' => 'Nate New']);

        $this->actingAs($actor);
        $asset = Asset::factory()->create(['owner_id' => $oldOwner->id, 'state' => AssetState::NEW->value]);
        $asset->update(['owner_id' => $newOwner->id]);

        $history = AssetHistory::for($asset->fresh());

        // newest first: the update entry is first
        $updated = $history[0];
        $this->assertSame('updated', $updated['event']);
        $this->assertSame('Cara Causer', $updated['causer_name']);
        $ownerChange = collect($updated['changes'])->firstWhere('field', 'owner_id');
        $this->assertNotNull($ownerChange);
        $this->assertSame('Olive Old', $ownerChange['old']);
        $this->assertSame('Nate New', $ownerChange['new']);
    }

    public function test_resolves_enum_state_labels(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $asset->update(['state' => AssetState::DEFECT->value]);

        $stateChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'state');
        $this->assertSame(AssetState::NEW->getLabel(), $stateChange['old']);   // 'Neu'
        $this->assertSame(AssetState::DEFECT->getLabel(), $stateChange['new']); // 'Defekt'
    }

    public function test_missing_referenced_record_resolves_to_removed(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $gone = Person::factory()->create();
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['owner_id' => null]);
        $asset->update(['owner_id' => $gone->id]);
        $gone->delete();

        $ownerChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'owner_id');
        $this->assertSame('(removed)', $ownerChange['new']);
    }

    public function test_null_causer_shows_system(): void
    {
        // No actingAs -> no auth user -> null causer.
        $asset = Asset::factory()->create();
        $asset->update(['state' => AssetState::STORAGE->value]);

        $entry = AssetHistory::for($asset->fresh())[0];
        $this->assertSame('System', $entry['causer_name']);
    }

    public function test_excludes_semantic_observer_entries(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $newOwner = Person::factory()->create();

        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $asset->update(['owner_id' => $newOwner->id]);

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
        $ownerChange = collect($updatedEntries->first()['changes'])->firstWhere('field', 'owner_id');
        $this->assertNotNull($ownerChange);
        $this->assertSame($newOwner->name, $ownerChange['new']);
    }

    public function test_former_user_causer(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $second = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($actor);
        $asset = Asset::factory()->create();

        $this->actingAs($second);
        $asset->update(['state' => AssetState::DEFECT->value]);

        $second->delete();

        $entry = AssetHistory::for($asset->fresh())[0];
        $this->assertSame('Former user', $entry['causer_name']);
    }

    public function test_excludes_attachment_activity_rows(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
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

    public function test_invoice_change_shows_invoice_label(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create(['invoice' => 'INV-OLD']);
        $asset->update(['invoice' => 'INV-NEW']);

        $invoiceChange = collect(AssetHistory::for($asset->fresh())[0]['changes'])->firstWhere('field', 'invoice');
        $this->assertNotNull($invoiceChange);
        $this->assertSame('Invoice', $invoiceChange['label']);
        $this->assertSame('INV-OLD', $invoiceChange['old']);
        $this->assertSame('INV-NEW', $invoiceChange['new']);
    }

    public function test_show_returns_history_prop(): void
    {
        $actor = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($actor);
        $asset = Asset::factory()->create();
        $asset->update(['state' => AssetState::LEND->value]);

        $this->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('assets/show')
                ->has('history', fn ($h) => $h->has('0.event')->has('0.causer_name')->has('0.changes')->etc())
                ->etc());
    }
}
