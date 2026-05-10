<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\EntraTenantMismatchException;
use App\Models\User;
use App\Services\Auth\EntraIdAuthService;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraIdAuthServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

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

    public function test_assert_tenant_matches_passes_when_tid_matches_config(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $svc = new EntraIdAuthService();

        $svc->assertTenantMatches($this->makeSocialiteUser());

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_tenant_matches_throws_on_mismatch(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService();

        $this->expectException(EntraTenantMismatchException::class);

        $svc->assertTenantMatches($this->makeSocialiteUser([
            'user' => ['tid' => 'wrong-tenant'],
        ]));
    }

    public function test_assert_tenant_matches_throws_when_tid_missing(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService();

        $this->expectException(EntraTenantMismatchException::class);

        $u = $this->makeSocialiteUser();
        $u->user = []; // no tid claim
        $svc->assertTenantMatches($u);
    }

    public function test_resolve_user_returns_existing_user_matched_by_entra_id(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $existing = User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'email'         => 'unrelated@elsewhere.test',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();
        $resolved = $svc->resolveUser($this->makeSocialiteUser());

        $this->assertTrue($existing->is($resolved));
    }
}
