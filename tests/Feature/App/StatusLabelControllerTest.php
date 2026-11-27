<?php

namespace Tests\Feature\App;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatusLabelControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->superuser()->create(['login_enabled' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Out for diagnostics',
            'type' => 'undeployable',
            'notes' => 'At the vendor.',
            'show_in_nav' => false,
            'default_label' => false,
            ...$overrides,
        ];
    }

    public function test_the_former_states_are_seeded_as_system_labels(): void
    {
        $this->assertSame(8, StatusLabel::whereNotNull('system_key')->count());
        $this->assertSame('In Benutzung', StatusLabel::system(SystemStatus::IN_USE)->name);
        $this->assertSame('deployable', StatusLabel::system(SystemStatus::STORAGE)->type->value);
        $this->assertSame('archived', StatusLabel::system(SystemStatus::SOLD)->type->value);
        $this->assertTrue(StatusLabel::default()->is(StatusLabel::system(SystemStatus::NEW)));
    }

    public function test_index_lists_labels_with_type_and_asset_count(): void
    {
        Asset::factory()->count(2)->inStatus(SystemStatus::DEFECT)->create();

        $this->actingAs($this->user)->get('/app/status-labels?search=Defekt')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('status-labels/index')
                ->has('labels.data', 1)
                ->where('labels.data.0.type', 'undeployable')
                ->where('labels.data.0.system', true)
                ->where('labels.data.0.assets_count', 2));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/status-labels')->assertRedirect();
    }

    public function test_store_turns_the_type_into_flags_and_stamps_the_creator(): void
    {
        $this->actingAs($this->user)->post('/app/status-labels', $this->payload(['type' => 'pending']))
            ->assertRedirect('/app/status-labels')
            ->assertSessionHasNoErrors();

        $label = StatusLabel::where('name', 'Out for diagnostics')->sole();
        $this->assertFalse($label->deployable);
        $this->assertTrue($label->pending);
        $this->assertFalse($label->archived);
        $this->assertNull($label->system_key);
        $this->assertSame($this->user->id, $label->created_by);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user)->post('/app/status-labels', $this->payload([
            'name' => str_repeat('x', 101), 'type' => 'broken', 'show_in_nav' => 'maybe',
        ]))->assertSessionHasErrors(['name', 'type', 'show_in_nav']);
    }

    public function test_system_key_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->user)->post('/app/status-labels', $this->payload(['system_key' => 'in-use']));

        $this->assertNull(StatusLabel::where('name', 'Out for diagnostics')->value('system_key'));
        $this->assertSame('In Benutzung', StatusLabel::system(SystemStatus::IN_USE)->name);
    }

    public function test_only_one_label_is_the_default(): void
    {
        $this->actingAs($this->user)->post('/app/status-labels', $this->payload(['type' => 'deployable', 'default_label' => true]));

        $this->assertSame(['Out for diagnostics'], StatusLabel::where('default_label', true)->pluck('name')->all());

        // New assets start in it.
        $this->actingAs($this->user)->get('/app/assets/create')
            ->assertInertia(fn (Assert $p) => $p->where('defaultStatusId', StatusLabel::default()->id));
    }

    public function test_system_labels_can_be_renamed_but_not_deleted(): void
    {
        $inUse = StatusLabel::system(SystemStatus::IN_USE);

        $this->actingAs($this->user)->put("/app/status-labels/{$inUse->id}", $this->payload(['name' => 'Im Einsatz', 'type' => 'deployable']))
            ->assertRedirect('/app/status-labels');
        $this->assertSame('Im Einsatz', $inUse->fresh()->name);
        $this->assertSame(SystemStatus::IN_USE, $inUse->fresh()->system_key);

        $this->actingAs($this->user)->delete("/app/status-labels/{$inUse->id}")
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($inUse);
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $label = StatusLabel::factory()->create();

        $this->actingAs($this->user)->delete("/app/status-labels/{$label->id}")
            ->assertRedirect('/app/status-labels');

        $this->assertSoftDeleted($label);
        $this->assertSame($this->user->id, StatusLabel::withTrashed()->find($label->id)->deleted_by);
    }

    public function test_labels_shown_in_nav_are_shared_with_their_asset_count(): void
    {
        $label = StatusLabel::factory()->create(['name' => 'Zu prüfen', 'show_in_nav' => true]);
        Asset::factory()->count(3)->create(['status_id' => $label->id]);

        $this->actingAs($this->user)->get('/app/status-labels')
            ->assertInertia(fn (Assert $p) => $p->where('statusNav', [['id' => $label->id, 'name' => 'Zu prüfen', 'count' => 3]]));
    }

    public function test_assets_keep_a_deleted_status_but_cannot_be_moved_into_one(): void
    {
        $label = StatusLabel::factory()->create();
        $asset = Asset::factory()->create(['status_id' => $label->id]);
        $label->delete();
        $payload = [];

        $this->actingAs($this->user)->put("/app/assets/{$asset->id}", [...$payload, 'status_id' => $label->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->user)->get("/app/assets/{$asset->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('statusOptions', fn ($o) => collect($o)->contains('value', $label->id)));

        $other = Asset::factory()->create();
        $this->actingAs($this->user)->put("/app/assets/{$other->id}", [...$payload, 'status_id' => $label->id])
            ->assertSessionHasErrors('status_id');
    }
}
