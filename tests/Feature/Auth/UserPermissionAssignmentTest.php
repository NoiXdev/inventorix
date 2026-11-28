<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureLoginEnabled;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserPermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const LAST = 'At least one active superuser must remain.';

    private const OWN = 'You cannot remove your own superuser access.';

    private function superuser(array $attributes = []): User
    {
        return User::factory()->superuser()->create(['login_enabled' => true, ...$attributes]);
    }

    private function admin(): User
    {
        return User::factory()->withPermissions(['admin' => 1])->create(['login_enabled' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(User $user, array $extra = []): array
    {
        return [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'login_enabled' => (bool) $user->login_enabled,
            ...$extra,
        ];
    }

    private function privilegedGroup(): Group
    {
        return Group::where('name', 'IT-Admin')->firstOrFail();
    }

    private function plainGroup(): Group
    {
        return Group::where('name', 'Read only')->firstOrFail();
    }

    // --- Protecting privileged users (policy) ---

    public function test_admin_cannot_edit_update_reset_or_delete_a_superuser(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $target = $this->superuser(['email' => 'boss@example.test']);

        $this->actingAs($admin)->get("/app/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($admin)->put("/app/users/{$target->id}", $this->payload($target, ['email' => 'evil@example.test']))
            ->assertForbidden();
        $this->actingAs($admin)->post("/app/users/{$target->id}/send-reset")->assertForbidden();
        $this->actingAs($admin)->delete("/app/users/{$target->id}")->assertForbidden();

        $this->assertSame('boss@example.test', $target->refresh()->email);
        Notification::assertNothingSent();
    }

    public function test_users_edit_actor_cannot_touch_a_user_with_admin_from_a_group(): void
    {
        $actor = User::factory()->withPermissions(['users.view' => 1, 'users.edit' => 1, 'users.delete' => 1])->create();
        $target = User::factory()->create(['login_enabled' => true]);
        $target->groups()->attach($this->privilegedGroup());

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['email' => 'evil@example.test']))
            ->assertForbidden();
        $this->actingAs($actor)->delete("/app/users/{$target->id}")->assertForbidden();
    }

    public function test_superuser_can_edit_reset_and_delete_another_superuser(): void
    {
        Notification::fake();
        $actor = $this->superuser();
        $target = $this->superuser();

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['email' => 'new@example.test']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $target->refresh()->email);

        $this->actingAs($actor)->post("/app/users/{$target->id}/send-reset")->assertRedirect();
        $this->actingAs($actor)->delete("/app/users/{$target->id}")->assertRedirect('/app/users');
        $this->assertNull(User::find($target->id));
    }

    public function test_admin_can_edit_a_plain_user(): void
    {
        $target = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, ['email' => 'plain@example.test']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('plain@example.test', $target->refresh()->email);
    }

    public function test_without_admin_only_users_with_a_subset_of_ones_own_permissions_can_be_managed(): void
    {
        Notification::fake();
        $actor = User::factory()->withPermissions(['users.view' => 1, 'users.edit' => 1, 'users.delete' => 1, 'assets.view' => 1])
            ->create(['login_enabled' => true]);
        // Holds a permission the actor lacks: taking over this account would escalate.
        $stronger = User::factory()->withPermissions(['assets.view' => 1, 'assets.delete' => 1])
            ->create(['login_enabled' => true, 'email' => 'stronger@example.test']);
        $viaGroup = User::factory()->create(['login_enabled' => true]);
        $viaGroup->groups()->attach(Group::factory()->withPermissions(['import'])->create());
        $weaker = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);
        $none = User::factory()->withoutLogin()->create();

        foreach ([$stronger, $viaGroup] as $target) {
            $this->actingAs($actor)->get("/app/users/{$target->id}/edit")->assertForbidden();
            $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['email' => 'evil@example.test']))
                ->assertForbidden();
            $this->actingAs($actor)->post("/app/users/{$target->id}/send-reset")->assertForbidden();
            $this->actingAs($actor)->delete("/app/users/{$target->id}")->assertForbidden();
        }
        $this->assertSame('stronger@example.test', $stronger->refresh()->email);
        Notification::assertNothingSent();

        $this->actingAs($actor)->put("/app/users/{$weaker->id}", $this->payload($weaker, ['email' => 'weaker@example.test']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('weaker@example.test', $weaker->refresh()->email);
        $this->actingAs($actor)->get("/app/users/{$none->id}/edit")->assertOk();
        // Oneself is always a subset of oneself.
        $this->actingAs($actor)->get("/app/users/{$actor->id}/edit")->assertOk();
    }

    public function test_admin_manages_non_privileged_users_with_any_permissions(): void
    {
        $target = User::factory()->withPermissions(['assets.delete' => 1, 'import' => 1])->create(['login_enabled' => true]);

        $this->actingAs($this->admin())->get("/app/users/{$target->id}/edit")->assertOk();
    }

    public function test_show_page_says_whether_the_user_can_be_managed(): void
    {
        $actor = User::factory()->withPermissions(['users.view' => 1, 'users.edit' => 1])->create();
        $stronger = User::factory()->withPermissions(['assets.delete' => 1])->create();
        $plain = User::factory()->withoutLogin()->create();

        $this->actingAs($actor)->get("/app/users/{$stronger->id}")
            ->assertInertia(fn (Assert $p) => $p->where('canManage', false));
        $this->actingAs($actor)->get("/app/users/{$plain->id}")
            ->assertInertia(fn (Assert $p) => $p->where('canManage', true));
    }

    // --- Assigning groups and permissions ---

    public function test_users_edit_actor_cannot_send_groups_or_permissions(): void
    {
        $actor = User::factory()->withPermissions(['users.edit' => 1])->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [$this->plainGroup()->id],
            'permissions' => ['assets.view' => 1],
        ]))->assertSessionHasErrors(['groups', 'permissions']);

        $this->assertCount(0, $target->groups()->get());
        $this->assertNull($target->refresh()->permissions);
    }

    public function test_users_create_actor_cannot_send_groups_on_store(): void
    {
        $actor = User::factory()->withPermissions(['users.create' => 1])->create();

        $this->actingAs($actor)->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'login_enabled' => false,
            'groups' => [$this->plainGroup()->id],
        ])->assertSessionHasErrors('groups');

        $this->assertDatabaseMissing('users', ['name' => 'Ada Lovelace']);
    }

    public function test_admin_assigns_a_plain_group_and_it_is_logged(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [$this->plainGroup()->id],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['Read only'], $target->groups()->pluck('name')->all());
        $this->assertTrue($target->refresh()->hasAccess('assets.view'));

        $activity = Activity::where('log_name', 'user')->where('event', 'groups_changed')->latest('id')->firstOrFail();
        $this->assertSame($target->id, $activity->subject_id);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(['groups' => []], $activity->attribute_changes['old']);
        $this->assertSame(['groups' => ['Read only']], $activity->attribute_changes['attributes']);
    }

    public function test_admin_assigns_groups_and_permissions_on_create(): void
    {
        $this->actingAs($this->admin())->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'login_enabled' => false,
            'groups' => [$this->plainGroup()->id],
            'permissions' => ['assets.delete' => 1, 'assets.view' => -1],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('name', 'Ada Lovelace')->firstOrFail();
        $this->assertSame(['Read only'], $user->groups->pluck('name')->all());
        $this->assertSame(['assets.delete' => 1, 'assets.view' => -1], $user->permissions);
    }

    public function test_admin_cannot_add_a_privileged_group(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [$this->privilegedGroup()->id],
        ]))->assertSessionHasErrors('groups');

        $this->assertCount(0, $target->groups()->get());
    }

    public function test_admin_cannot_grant_superuser_or_admin(): void
    {
        $target = User::factory()->create();

        foreach (['superuser', 'admin'] as $key) {
            $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, [
                'permissions' => [$key => 1],
            ]))->assertSessionHasErrors('permissions');
        }

        $this->assertNull($target->refresh()->permissions);
    }

    public function test_admin_keeps_existing_privileged_denies_it_does_not_send(): void
    {
        // In IT-Admin but admin denied on the user: not privileged, so an admin may edit them,
        // but omitting the admin key must not lift the deny.
        $target = User::factory()->withPermissions(['admin' => -1])->create();
        $target->groups()->attach($this->privilegedGroup());

        $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [$this->privilegedGroup()->id],
            'permissions' => ['assets.view' => 1],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $target->refresh()->load('groups');
        $this->assertEquals(['admin' => -1, 'assets.view' => 1], $target->permissions);
        $this->assertFalse($target->hasAccess('admin'));
    }

    public function test_admin_cannot_lift_a_privileged_deny_or_drop_a_privileged_group(): void
    {
        $target = User::factory()->withPermissions(['admin' => -1])->create();
        $target->groups()->attach($this->privilegedGroup());

        $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, [
            'permissions' => ['admin' => 1],
        ]))->assertSessionHasErrors('permissions');

        $this->actingAs($this->admin())->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [],
        ]))->assertSessionHasErrors('groups');

        $this->assertSame(['admin' => -1], $target->refresh()->permissions);
        $this->assertCount(1, $target->groups()->get());
    }

    public function test_admin_cannot_change_own_groups(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/app/users/{$admin->id}", $this->payload($admin, [
            'groups' => [$this->plainGroup()->id],
        ]))->assertForbidden();

        $this->assertCount(0, $admin->groups()->get());
    }

    public function test_superuser_can_assign_a_privileged_group_and_grant_admin(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->superuser())->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => [$this->privilegedGroup()->id],
            'permissions' => ['superuser' => 1],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $target->refresh()->load('groups');
        $this->assertTrue($target->isSuperuser());
        $this->assertSame(['IT-Admin'], $target->groups->pluck('name')->all());
    }

    public function test_superuser_denies_a_permission_the_group_grants(): void
    {
        $target = User::factory()->create();
        $target->groups()->attach(Group::factory()->withPermissions(['assets.delete'])->create());

        $this->actingAs($this->superuser())->put("/app/users/{$target->id}", $this->payload($target, [
            'permissions' => ['assets.delete' => -1],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(['assets.delete' => -1], $target->permissions);
        $this->assertFalse($target->hasAccess('assets.delete'));
    }

    public function test_permission_keys_and_values_are_validated(): void
    {
        $target = User::factory()->create();
        $actor = $this->superuser();

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, [
            'permissions' => ['assets.fly' => 1],
        ]))->assertSessionHasErrors('permissions');

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, [
            'permissions' => ['assets.view' => 2],
        ]))->assertSessionHasErrors('permissions');

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, [
            'groups' => ['00000000-0000-0000-0000-000000000000'],
        ]))->assertSessionHasErrors('groups.0');

        $this->assertNull($target->refresh()->permissions);
    }

    public function test_clearing_the_permission_map(): void
    {
        $target = User::factory()->withPermissions(['assets.view' => 1])->create();

        $this->actingAs($this->superuser())->put("/app/users/{$target->id}", $this->payload($target, [
            'permissions' => [],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($target->refresh()->permissions);
    }

    public function test_omitting_groups_and_permissions_leaves_them_untouched(): void
    {
        $target = User::factory()->withPermissions(['assets.view' => 1])->create();
        $target->groups()->attach($this->plainGroup());

        $this->actingAs($this->superuser())->put("/app/users/{$target->id}", $this->payload($target, ['firstname' => 'Renamed']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Renamed', $target->firstname);
        $this->assertSame(['assets.view' => 1], $target->permissions);
        $this->assertCount(1, $target->groups()->get());
    }

    // --- Guard rails ---

    public function test_last_superuser_cannot_demote_self(): void
    {
        $me = $this->superuser();

        $this->actingAs($me)->put("/app/users/{$me->id}", $this->payload($me, ['permissions' => []]))
            ->assertSessionHasErrors(['permissions' => self::LAST]);

        $this->assertTrue($me->refresh()->isSuperuser());
    }

    public function test_last_superuser_cannot_leave_the_group_that_makes_them_superuser(): void
    {
        $group = Group::factory()->withPermissions(['superuser'])->create();
        $me = User::factory()->create(['login_enabled' => true]);
        $me->groups()->attach($group);

        $this->actingAs($me)->put("/app/users/{$me->id}", $this->payload($me, ['groups' => []]))
            ->assertSessionHasErrors(['groups' => self::LAST]);

        $this->assertCount(1, $me->groups()->get());
    }

    public function test_superuser_cannot_remove_own_superuser_even_when_another_exists(): void
    {
        $this->superuser();
        $me = $this->superuser();

        $this->actingAs($me)->put("/app/users/{$me->id}", $this->payload($me, ['permissions' => ['superuser' => -1]]))
            ->assertSessionHasErrors(['permissions' => self::OWN]);

        $this->assertSame(['superuser' => 1], $me->refresh()->permissions);
    }

    public function test_another_superuser_can_be_demoted_while_one_active_remains(): void
    {
        $actor = $this->superuser();
        $target = $this->superuser();

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['permissions' => []]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($target->refresh()->isSuperuser());
    }

    public function test_last_active_superuser_cannot_be_demoted_disabled_or_deleted(): void
    {
        // The actor is a superuser whose own login is disabled, so the target is the last active one.
        // EnsureLoginEnabled would sign a disabled actor out; it is bypassed here.
        $this->withoutMiddleware(EnsureLoginEnabled::class);
        $actor = $this->superuser(['login_enabled' => false]);
        $target = $this->superuser();

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['permissions' => []]))
            ->assertSessionHasErrors(['permissions' => self::LAST]);

        $this->actingAs($actor)->put("/app/users/{$target->id}", $this->payload($target, ['login_enabled' => false]))
            ->assertSessionHasErrors(['login_enabled' => self::LAST]);

        $this->actingAs($actor)->delete("/app/users/{$target->id}")
            ->assertSessionHasErrors(['user' => self::LAST]);

        $target->refresh();
        $this->assertTrue($target->isSuperuser());
        $this->assertTrue($target->login_enabled);
    }

    // --- Inertia props ---

    public function test_edit_props_for_a_superuser_actor(): void
    {
        $group = Group::factory()->withPermissions(['assets.delete'])->create(['name' => 'Deleters']);
        $target = User::factory()->withPermissions(['assets.view' => 1, 'assets.edit' => -1])->create();
        $target->groups()->attach($group);

        $this->actingAs($this->superuser())->get("/app/users/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/edit')
                ->where('user.groups', [$group->id])
                ->where('user.permissions', ['assets.view' => 1, 'assets.edit' => -1])
                ->where('canAssign', true)
                ->where('canAssignPrivileged', true)
                ->has('catalogue')
                ->where('groupOptions', fn ($options) => collect($options)->firstWhere('label', 'IT-Admin')['privileged'] === true
                    && collect($options)->firstWhere('label', 'Read only')['privileged'] === false
                    && collect($options)->firstWhere('label', 'Deleters')['value'] === $group->id)
                ->where('effective', fn ($e) => $e['assets.view'] === ['granted' => true, 'via' => 'user'])
                ->where('effective', fn ($e) => $e['assets.edit'] === ['granted' => false, 'via' => null])
                ->where('effective', fn ($e) => $e['assets.delete'] === ['granted' => true, 'via' => 'Deleters'])
                ->where('effective', fn ($e) => $e['superuser'] === ['granted' => false, 'via' => null])
                ->etc());
    }

    public function test_edit_props_effective_via_admin_and_superuser(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->attach($this->privilegedGroup());
        $super = User::factory()->superuser()->create();
        $actor = $this->superuser();

        $this->actingAs($actor)->get("/app/users/{$admin->id}/edit")
            ->assertInertia(fn (Assert $p) => $p
                ->where('effective', fn ($e) => $e['admin'] === ['granted' => true, 'via' => 'IT-Admin'])
                ->where('effective', fn ($e) => $e['assets.view'] === ['granted' => true, 'via' => 'admin'])
                ->where('effective', fn ($e) => $e['superuser'] === ['granted' => false, 'via' => null])
                ->etc());

        $this->actingAs($actor)->get("/app/users/{$super->id}/edit")
            ->assertInertia(fn (Assert $p) => $p
                ->where('effective', fn ($e) => $e['superuser'] === ['granted' => true, 'via' => 'user'])
                ->where('effective', fn ($e) => $e['admin'] === ['granted' => true, 'via' => 'superuser'])
                ->where('effective', fn ($e) => $e['assets.view'] === ['granted' => true, 'via' => 'superuser'])
                ->etc());
    }

    public function test_edit_and_create_props_for_admin_and_plain_actors(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())->get("/app/users/{$target->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('canAssign', true)->where('canAssignPrivileged', false)->etc());

        $this->actingAs($this->admin())->get('/app/users/create')
            ->assertInertia(fn (Assert $p) => $p->component('users/create')
                ->where('canAssign', true)->where('canAssignPrivileged', false)
                ->has('groupOptions')->has('catalogue')->etc());

        $plain = User::factory()->withPermissions(['users.edit' => 1, 'users.create' => 1])->create();
        $this->actingAs($plain)->get("/app/users/{$target->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('canAssign', false)->where('canAssignPrivileged', false)->etc());
    }

    public function test_show_props_include_groups_and_effective(): void
    {
        $target = User::factory()->withPermissions(['assets.delete' => 1])->create();
        $target->groups()->attach($this->plainGroup());

        $this->actingAs($this->superuser())->get("/app/users/{$target->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/show')
                ->where('groups', ['Read only'])
                ->where('effective', fn ($e) => $e['assets.view'] === true)
                ->where('effective', fn ($e) => $e['assets.delete'] === true)
                ->where('effective', fn ($e) => $e['assets.edit'] === false)
                ->where('effective', fn ($e) => $e['superuser'] === false)
                ->etc());
    }
}
