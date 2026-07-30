<?php

// tests/Feature/App/PasswordResetTest.php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_url_points_into_the_app(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/app/reset-password/')
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_reset_page_is_reachable_as_guest(): void
    {
        $this->get('/app/reset-password/some-token?email=a@b.test')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('auth/reset-password')
                ->where('token', 'some-token')->where('email', 'a@b.test'));
    }

    public function test_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/app/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/app/login');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected_and_password_unchanged(): void
    {
        $user = User::factory()->create(['password' => bcrypt('original-pw')]);

        $this->post('/app/reset-password', [
            'token' => 'wrong-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-pw', $user->fresh()->password));
    }
}
