<?php

namespace Tests\Feature\Auth;

use App\Models\User;
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
        $u = new SocialiteUser();
        $u->id    = $overrides['id']    ?? 'oid-abc-123';
        $u->email = $overrides['email'] ?? 'alice@3b.de';
        $u->name  = $overrides['name']  ?? 'Alice Example';
        $u->user  = array_merge([
            'tid'               => 'test-tenant-id',
            'givenName'         => 'Alice',
            'surname'           => 'Example',
            'displayName'       => 'Alice Example',
            'mail'              => 'alice@3b.de',
            'userPrincipalName' => 'alice@3b.de',
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
            'entra_id'      => 'oid-abc-123',
            'email'         => 'unrelated@elsewhere.test',
            'firstname'     => 'Old',
            'lastname'      => 'Name',
            'name'          => 'Old Name',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->firstname);
        $this->assertSame('Example', $fresh->lastname);
        $this->assertSame('alice@3b.de', $fresh->email);
    }

    public function test_callback_links_existing_user_by_email_when_entra_id_is_null(): void
    {
        $user = User::factory()->create([
            'entra_id'      => null,
            'email'         => 'alice@3b.de',
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
            'entra_id'      => 'oid-abc-123',
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
            'entra_id'      => 'oid-abc-123',
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
}
