<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GateTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_abilities_follow_has_access(): void
    {
        $user = User::factory()->withPermissions(['assets.edit' => 1])->create();

        $this->assertTrue(Gate::forUser($user)->allows('assets.edit'));
        $this->assertFalse(Gate::forUser($user)->allows('assets.delete'));
        $this->assertTrue(Gate::forUser(User::factory()->superuser()->create())->allows('assets.delete'));
    }

    public function test_unknown_ability_falls_through_the_before_hook(): void
    {
        $user = User::factory()->superuser()->create();

        $this->assertFalse(Gate::forUser($user)->allows('not.in.catalogue'));

        Gate::define('custom.ability', fn () => true);
        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('custom.ability'));
    }

    public function test_view_horizon_is_superuser_only(): void
    {
        $admin = User::factory()->withPermissions(['admin' => 1])->create();
        $superuser = User::factory()->superuser()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($superuser)->allows('viewHorizon'));
    }

    public function test_auth_can_is_shared_with_inertia(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => 1])->create();

        $response = $this->actingAs($user)->get('/app')->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['auth']['can']['assets.view']);
        $this->assertFalse($props['auth']['can']['assets.edit']);
        $this->assertFalse($props['auth']['can']['superuser']);
        $this->assertArrayNotHasKey('permissions', $props['auth']['user']);
        $this->assertStringNotContainsString('"permissions"', json_encode($props));
    }
}
