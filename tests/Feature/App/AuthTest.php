<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_inertia_login_page(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_logs_in_a_user_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => true]);

        $this->post('/app/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/app');

        $this->assertAuthenticatedAs($user);
    }

    public function test_rejects_a_disabled_account(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => false]);

        $this->post('/app/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_rejects_wrong_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => true]);

        $this->post('/app/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => true]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/app/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/app/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_redirects_unauthenticated_visitors_from_app_to_app_login(): void
    {
        $this->get('/app')->assertRedirect('/app/login');
    }

    public function test_logs_out(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($user)->post('/app/logout')->assertRedirect('/app/login');

        $this->assertGuest();
    }
}
