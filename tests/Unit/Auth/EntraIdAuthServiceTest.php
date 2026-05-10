<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\EntraTenantMismatchException;
use App\Services\Auth\EntraIdAuthService;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraIdAuthServiceTest extends TestCase
{
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
}
