<?php

namespace Tests\Unit\Auth;

use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasAccessTest extends TestCase
{
    use RefreshDatabase;

    private function inGroup(User $user, array $grants): Group
    {
        $group = Group::factory()->withPermissions($grants)->create();
        $user->groups()->attach($group);

        return $group;
    }

    public function test_no_groups_and_no_map_means_no_access(): void
    {
        $this->assertFalse(User::factory()->create()->hasAccess('assets.view'));
    }

    public function test_user_grant_gives_access(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => 1])->create();

        $this->assertTrue($user->hasAccess('assets.view'));
        $this->assertTrue($user->hasAccess(Permission::ASSETS_VIEW));
    }

    public function test_user_deny_beats_group_grant(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => -1])->create();
        $this->inGroup($user, ['assets.view']);

        $this->assertFalse($user->hasAccess('assets.view'));
    }

    public function test_group_grant_gives_access(): void
    {
        $user = User::factory()->create();
        $this->inGroup($user, ['assets.edit']);

        $this->assertTrue($user->hasAccess('assets.edit'));
        $this->assertFalse($user->hasAccess('assets.delete'));
    }

    public function test_any_of_several_groups_may_grant(): void
    {
        $user = User::factory()->create();
        $this->inGroup($user, ['assets.view']);
        $this->inGroup($user, ['assets.edit']);

        $this->assertTrue($user->hasAccess('assets.edit'));
    }

    public function test_admin_group_covers_everything_but_superuser_only(): void
    {
        $user = User::factory()->create();
        $this->inGroup($user, ['admin']);

        $this->assertTrue($user->hasAccess('assets.delete'));
        $this->assertFalse($user->hasAccess('superuser'));
    }

    public function test_user_deny_on_admin_removes_its_reach(): void
    {
        $user = User::factory()->withPermissions(['admin' => -1])->create();
        $this->inGroup($user, ['admin']);

        $this->assertFalse($user->hasAccess('assets.delete'));
    }

    public function test_user_deny_does_not_beat_admin_from_a_group(): void
    {
        $user = User::factory()->withPermissions(['assets.delete' => -1])->create();
        $this->inGroup($user, ['admin']);

        $this->assertTrue($user->hasAccess('assets.delete'));
    }

    public function test_superuser_has_every_permission(): void
    {
        $user = User::factory()->withPermissions(['superuser' => 1])->create();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($user->hasAccess($permission), $permission->value);
        }
        $this->assertTrue($user->hasAccess('admin'));
        $this->assertTrue($user->isSuperuser());
    }

    public function test_user_deny_beats_superuser_from_group(): void
    {
        $user = User::factory()->withPermissions(['superuser' => -1])->create();
        $this->inGroup($user, ['superuser']);

        $this->assertFalse($user->isSuperuser());
    }

    public function test_effective_permissions_cover_exactly_the_catalogue(): void
    {
        $user = User::factory()->create();
        $effective = $user->effectivePermissions();

        $this->assertEqualsCanonicalizing(array_map(fn (Permission $p) => $p->value, Permission::cases()), array_keys($effective));
        $this->assertNotContains(true, $effective);

        $this->assertTrue(User::factory()->superuser()->create()->effectivePermissions()['users.delete']);
    }

    public function test_catalogue_sections_are_in_order(): void
    {
        $this->assertSame(
            ['global', 'assets', 'incidents', 'handovers', 'licenses', 'accessories', 'consumables', 'components', 'kits', 'models', 'categories', 'manufacturers', 'suppliers', 'locations', 'statuslabels', 'customfields', 'depreciations', 'departments', 'users'],
            array_column(Permission::catalogue(), 'section'),
        );
    }

    public function test_only_superuser_and_admin_are_superuser_only(): void
    {
        $only = array_filter(Permission::cases(), fn (Permission $p) => $p->isSuperuserOnly());

        $this->assertEqualsCanonicalizing(['superuser', 'admin'], array_map(fn (Permission $p) => $p->value, $only));
    }
}
