<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Settings\AuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MicrosoftLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.microsoft-azure.enabled', true);
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
    }

    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $u = new SocialiteUser;
        $u->id = $overrides['id'] ?? 'oid-abc-123';
        $u->email = $overrides['email'] ?? 'alice@example.com';
        $u->name = $overrides['name'] ?? 'Alice Example';
        $u->user = array_merge([
            'tid' => 'test-tenant-id',
            'givenName' => 'Alice',
            'surname' => 'Example',
            'displayName' => 'Alice Example',
            'mail' => 'alice@example.com',
            'userPrincipalName' => 'alice@example.com',
        ], $overrides['user'] ?? []);

        return $u;
    }

    private function fakeSocialiteReturning(SocialiteUser $msUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($msUser);
        $driver->shouldReceive('scopes')->andReturnSelf();
        Socialite::shouldReceive('driver')->with('microsoft-azure')->andReturn($driver);
    }

    public function test_callback_logs_in_existing_user_matched_by_entra_id(): void
    {
        $user = User::factory()->create([
            'entra_id' => 'oid-abc-123',
            'email' => 'unrelated@elsewhere.test',
            'firstname' => 'Old',
            'lastname' => 'Name',
            'name' => 'Old Name',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->firstname);
        $this->assertSame('Example', $fresh->lastname);
        $this->assertSame('alice@example.com', $fresh->email);
    }

    public function test_callback_links_existing_user_by_email_when_entra_id_is_null(): void
    {
        $user = User::factory()->create([
            'entra_id' => null,
            'email' => 'alice@example.com',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $this->get('/auth/microsoft/callback?code=fake&state=fake')
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('oid-abc-123', $user->fresh()->entra_id);
    }

    public function test_callback_rejects_unknown_user(): void
    {
        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error');
        $this->assertGuest();
    }

    public function test_callback_rejects_disabled_user(): void
    {
        User::factory()->create([
            'entra_id' => 'oid-abc-123',
            'login_enabled' => false,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('Your account is disabled. Contact an administrator.'));
        $this->assertGuest();
    }

    public function test_callback_rejects_wrong_tenant(): void
    {
        User::factory()->create([
            'entra_id' => 'oid-abc-123',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser([
            'user' => ['tid' => 'some-other-tenant'],
        ]));

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('This Microsoft account is not from the authorized tenant.'));
        $this->assertGuest();
    }

    public function test_callback_redirects_with_generic_error_on_unexpected_failure(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('boom'));
        $driver->shouldReceive('scopes')->andReturnSelf();
        Socialite::shouldReceive('driver')->with('microsoft-azure')->andReturn($driver);

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('Microsoft sign-in failed. Please try again.'));
        $this->assertGuest();
    }

    public function test_routes_return_404_when_feature_disabled(): void
    {
        config()->set('services.microsoft-azure.enabled', false);

        $auth = app(AuthSettings::class);
        $auth->microsoft_enabled = false;
        $auth->save();

        $this->get('/auth/microsoft/redirect')->assertNotFound();
        $this->get('/auth/microsoft/callback?code=x&state=y')->assertNotFound();
    }

    public function test_redirect_route_is_reachable_when_enabled_via_db_settings(): void
    {
        // Clear the config so only the DB-applied value can enable the feature.
        config()->set('services.microsoft-azure.enabled', false);

        $auth = app(AuthSettings::class);
        $auth->microsoft_enabled = true;
        $auth->microsoft_client_id = 'client-123';
        $auth->microsoft_client_secret = 'super-secret';
        $auth->microsoft_redirect = 'https://app.test/auth/microsoft/callback';
        $auth->microsoft_tenant = 'test-tenant-id';
        $auth->save();

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://login.microsoftonline.com/authorize'));
        Socialite::shouldReceive('driver')->with('microsoft-azure')->andReturn($driver);

        // ApplyRuntimeSettings on the route group applies microsoft_enabled=true from the DB,
        // so the controller's abort_unless passes (no 404).
        $this->get('/auth/microsoft/redirect')->assertRedirect();
    }

    public function test_redirect_route_is_404_when_disabled_via_db_settings(): void
    {
        config()->set('services.microsoft-azure.enabled', true); // env-style default

        $auth = app(AuthSettings::class);
        $auth->microsoft_enabled = false;
        $auth->microsoft_client_id = null;
        $auth->microsoft_client_secret = null;
        $auth->microsoft_redirect = null;
        $auth->microsoft_tenant = null;
        $auth->save();

        // The route middleware applies microsoft_enabled=false from the DB, overriding the config above.
        $this->get('/auth/microsoft/redirect')->assertNotFound();
    }

    public function test_login_page_renders_button_when_feature_enabled(): void
    {
        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertSeeText(__('Login via Entra ID'));
    }

    public function test_login_page_does_not_render_button_when_feature_disabled(): void
    {
        config()->set('services.microsoft-azure.enabled', false);

        $auth = app(AuthSettings::class);
        $auth->microsoft_enabled = false;
        $auth->save();

        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertDontSeeText(__('Login via Entra ID'));
    }
}
