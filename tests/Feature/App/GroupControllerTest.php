<?php

namespace Tests\Feature\App;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        return User::factory()->superuser()->create(['login_enabled' => true]);
    }

    public function test_index_lists_groups_with_user_count_and_summary(): void
    {
        $group = Group::factory()->create(['name' => 'Zeta', 'permissions' => ['assets.view' => 1, 'assets.edit' => 1]]);
        $group->users()->attach(User::factory()->create());

        $this->actingAs($this->superuser())->get('/app/groups')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('groups/index')
                ->where('groups.data', fn ($rows) => collect($rows)->contains(
                    fn ($r) => $r['name'] === 'Zeta' && $r['users_count'] === 1 && $r['summary'] === '2 permissions')
                    && collect($rows)->contains(fn ($r) => $r['name'] === 'IT-Admin' && $r['summary'] === 'Admin')));
    }

    public function test_create_page_gets_the_catalogue(): void
    {
        $this->actingAs($this->superuser())->get('/app/groups/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('groups/create')->has('catalogue'));
    }

    public function test_store_keeps_only_granted_keys(): void
    {
        $this->actingAs($this->superuser())->post('/app/groups', [
            'name' => 'Helpdesk',
            'notes' => 'Front line',
            'permissions' => ['assets.view' => 1, 'assets.edit' => 1],
        ])->assertRedirect('/app/groups');

        $group = Group::where('name', 'Helpdesk')->firstOrFail();
        $this->assertSame(['assets.view' => 1, 'assets.edit' => 1], $group->permissions);
        $this->assertSame('Front line', $group->notes);
    }

    public function test_store_rejects_unknown_keys_and_non_grant_values(): void
    {
        $this->actingAs($this->superuser())->post('/app/groups', ['name' => 'X', 'permissions' => ['nope' => 1]])
            ->assertSessionHasErrors('permissions.nope');
        $this->actingAs($this->superuser())->post('/app/groups', ['name' => 'Y', 'permissions' => ['assets.view' => -1]])
            ->assertSessionHasErrors('permissions.assets.view');
    }

    public function test_name_is_required_and_unique(): void
    {
        $this->actingAs($this->superuser())->post('/app/groups', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->superuser())->post('/app/groups', ['name' => 'IT-Admin'])->assertSessionHasErrors('name');
    }

    public function test_edit_and_update(): void
    {
        $group = Group::factory()->create(['name' => 'Old', 'permissions' => ['assets.view' => 1]]);
        $user = $this->superuser();

        $this->actingAs($user)->get("/app/groups/{$group->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('groups/edit')->where('group.name', 'Old')->has('catalogue'));

        $this->actingAs($user)->put("/app/groups/{$group->id}", ['name' => 'Old', 'permissions' => ['assets.view' => 1, 'assets.create' => 1]])
            ->assertRedirect('/app/groups');
        $this->assertSame(['assets.view' => 1, 'assets.create' => 1], $group->fresh()->permissions);
    }

    public function test_update_may_keep_its_own_name(): void
    {
        $group = Group::factory()->create(['name' => 'Same']);
        $this->actingAs($this->superuser())->put("/app/groups/{$group->id}", ['name' => 'Same'])
            ->assertSessionHasNoErrors();
    }

    public function test_destroy_detaches_users(): void
    {
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->users()->attach($member);

        $this->actingAs($this->superuser())->delete("/app/groups/{$group->id}")->assertRedirect('/app/groups');

        $this->assertNull(Group::find($group->id));
        $this->assertDatabaseMissing('group_user', ['user_id' => $member->id]);
        $this->assertNotNull($member->fresh());
    }

    public function test_admin_gets_403_on_every_route(): void
    {
        $admin = User::factory()->withPermissions(['admin' => 1])->create(['login_enabled' => true]);
        $group = Group::factory()->create();

        $this->actingAs($admin)->get('/app/groups')->assertForbidden();
        $this->actingAs($admin)->get('/app/groups/create')->assertForbidden();
        $this->actingAs($admin)->post('/app/groups', ['name' => 'X'])->assertForbidden();
        $this->actingAs($admin)->get("/app/groups/{$group->id}/edit")->assertForbidden();
        $this->actingAs($admin)->put("/app/groups/{$group->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($admin)->delete("/app/groups/{$group->id}")->assertForbidden();
    }

    public function test_update_cannot_remove_superuser_from_the_last_superuser_group(): void
    {
        $group = Group::factory()->create(['name' => 'Supers', 'permissions' => ['superuser' => 1]]);
        $actor = User::factory()->create(['login_enabled' => true]);
        $group->users()->attach($actor);
        // Only superuser-capable account is via this group; actor is the one acting.
        $this->assertTrue($actor->fresh()->isSuperuser());

        $this->actingAs($actor)->put("/app/groups/{$group->id}", ['name' => 'Supers', 'permissions' => ['assets.view' => 1]])
            ->assertSessionHasErrors(['permissions' => 'At least one active superuser must remain.']);

        $this->assertSame(['superuser' => 1], $group->fresh()->permissions);
    }

    public function test_update_removing_superuser_is_fine_when_another_superuser_remains(): void
    {
        $group = Group::factory()->create(['permissions' => ['superuser' => 1]]);
        $group->users()->attach(User::factory()->create(['login_enabled' => true]));

        $this->actingAs($this->superuser())->put("/app/groups/{$group->id}", ['name' => $group->name, 'permissions' => ['assets.view' => 1]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['assets.view' => 1], $group->fresh()->permissions);
    }

    public function test_destroy_cannot_remove_the_last_superuser_group(): void
    {
        $group = Group::factory()->create(['permissions' => ['superuser' => 1]]);
        $actor = User::factory()->create(['login_enabled' => true]);
        $group->users()->attach($actor);

        $this->actingAs($actor)->delete("/app/groups/{$group->id}")
            ->assertSessionHasErrors(['group' => 'At least one active superuser must remain.']);

        $this->assertNotNull(Group::find($group->id));
        $this->assertDatabaseHas('group_user', ['user_id' => $actor->id]);
    }
}
