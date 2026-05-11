<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\EntraLoginDisabledException;
use App\Exceptions\Auth\EntraTenantMismatchException;
use App\Exceptions\Auth\EntraUserNotProvisionedException;
use App\Models\User;
use App\Services\Auth\EntraIdAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraIdAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $u = new SocialiteUser;
        $u->id = array_key_exists('id', $overrides) ? $overrides['id'] : 'oid-abc-123';
        $u->email = array_key_exists('email', $overrides) ? $overrides['email'] : 'alice@3b.de';
        $u->name = array_key_exists('name', $overrides) ? $overrides['name'] : 'Alice Example';
        $u->user = array_merge([
            'tid' => 'test-tenant-id',
            'givenName' => 'Alice',
            'surname' => 'Example',
            'displayName' => 'Alice Example',
            'mail' => 'alice@3b.de',
            'userPrincipalName' => 'alice@3b.de',
        ], $overrides['user'] ?? []);

        return $u;
    }

    public function test_assert_tenant_matches_passes_when_tid_matches_config(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $svc = new EntraIdAuthService;

        $svc->assertTenantMatches($this->makeSocialiteUser());

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_tenant_matches_throws_on_mismatch(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService;

        $this->expectException(EntraTenantMismatchException::class);

        $svc->assertTenantMatches($this->makeSocialiteUser([
            'user' => ['tid' => 'wrong-tenant'],
        ]));
    }

    public function test_assert_tenant_matches_throws_when_tid_missing(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService;

        $this->expectException(EntraTenantMismatchException::class);

        $u = $this->makeSocialiteUser();
        $u->user = []; // no tid claim
        $svc->assertTenantMatches($u);
    }

    public function test_resolve_user_returns_existing_user_matched_by_entra_id(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $existing = User::factory()->create([
            'entra_id' => 'oid-abc-123',
            'email' => 'unrelated@elsewhere.test',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService;
        $resolved = $svc->resolveUser($this->makeSocialiteUser());

        $this->assertTrue($existing->is($resolved));
    }

    public function test_resolve_user_links_existing_user_by_email_when_entra_id_is_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $existing = User::factory()->create([
            'entra_id' => null,
            'email' => 'alice@3b.de',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService;
        $resolved = $svc->resolveUser($this->makeSocialiteUser());

        $this->assertTrue($existing->is($resolved));
        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }

    public function test_resolve_user_email_match_is_case_insensitive(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id' => null,
            'email' => 'Alice@3B.de',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService;
        $resolved = $svc->resolveUser($this->makeSocialiteUser([
            'email' => 'alice@3b.de',
        ]));

        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }

    public function test_resolve_user_falls_back_to_user_principal_name_when_mail_is_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id' => null,
            'email' => 'alice@3b.de',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService;
        $msUser = $this->makeSocialiteUser([
            'email' => null,
            'user' => ['mail' => null, 'userPrincipalName' => 'alice@3b.de'],
        ]);
        $resolved = $svc->resolveUser($msUser);

        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }

    public function test_resolve_user_throws_not_provisioned_when_no_match(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');

        $svc = new EntraIdAuthService;

        $this->expectException(EntraUserNotProvisionedException::class);

        $svc->resolveUser($this->makeSocialiteUser());
    }

    public function test_resolve_user_throws_not_provisioned_when_email_and_upn_are_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id' => null,
            'email' => 'alice@3b.de',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService;

        $this->expectException(EntraUserNotProvisionedException::class);

        $svc->resolveUser($this->makeSocialiteUser([
            'email' => null,
            'user' => ['mail' => null, 'userPrincipalName' => null],
        ]));
    }

    public function test_resolve_user_throws_login_disabled_when_user_is_disabled(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id' => 'oid-abc-123',
            'login_enabled' => false,
        ]);

        $svc = new EntraIdAuthService;

        $this->expectException(EntraLoginDisabledException::class);

        $svc->resolveUser($this->makeSocialiteUser());
    }

    public function test_sync_attributes_overwrites_firstname_lastname_name_email(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $user = User::factory()->create([
            'firstname' => 'Old',
            'lastname' => 'Name',
            'name' => 'Old Name',
            'email' => 'old@local.test',
            'entra_id' => 'oid-abc-123',
        ]);

        $svc = new EntraIdAuthService;
        $svc->syncAttributes($user, $this->makeSocialiteUser([
            'user' => [
                'givenName' => 'Alice',
                'surname' => 'Example',
                'displayName' => 'Alice Example',
                'mail' => 'alice@3b.de',
                'userPrincipalName' => 'alice@3b.de',
            ],
        ]));

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->firstname);
        $this->assertSame('Example', $fresh->lastname);
        $this->assertSame('Alice Example', $fresh->name);
        $this->assertSame('alice@3b.de', $fresh->email);
    }

    public function test_sync_attributes_uses_user_principal_name_when_mail_missing(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $user = User::factory()->create(['entra_id' => 'oid-abc-123']);

        $svc = new EntraIdAuthService;
        $svc->syncAttributes($user, $this->makeSocialiteUser([
            'user' => [
                'givenName' => 'Alice',
                'surname' => 'Example',
                'displayName' => 'Alice Example',
                'mail' => null,
                'userPrincipalName' => 'alice@3b.de',
            ],
        ]));

        $this->assertSame('alice@3b.de', $user->fresh()->email);
    }
}
