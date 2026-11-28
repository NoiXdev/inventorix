<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureLoginEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_user_whose_login_is_disabled_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($user)->get('/app')->assertOk();

        $user->forceFill(['login_enabled' => false])->save();

        $this->get('/app')
            ->assertRedirect(route('app.login'))
            ->assertSessionHas('error', 'This account is disabled.');
        $this->assertGuest();
    }

    public function test_public_routes_do_not_carry_the_middleware(): void
    {
        $this->get(route('app.login'))->assertOk();
    }

    public function test_disabling_login_in_the_user_form_rotates_the_remember_token(): void
    {
        $admin = User::factory()->superuser()->create(['login_enabled' => true]);
        $user = User::factory()->create(['login_enabled' => true, 'remember_token' => 'old-token', 'email' => 'ada@example.test']);

        $this->actingAs($admin)->put("/app/users/{$user->id}", [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.test', 'login_enabled' => false,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->login_enabled);
        $this->assertNotSame('old-token', $user->remember_token);
    }
}
