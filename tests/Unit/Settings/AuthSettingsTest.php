<?php

namespace Tests\Unit\Settings;

use App\Settings\AuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_and_round_trips_microsoft_settings(): void
    {
        $settings = app(AuthSettings::class);
        $settings->microsoft_enabled = true;
        $settings->microsoft_client_id = 'client-123';
        $settings->microsoft_client_secret = 'super-secret';
        $settings->microsoft_redirect = 'https://app.test/auth/microsoft/callback';
        $settings->microsoft_tenant = 'tenant-abc';
        $settings->save();

        $fresh = app(AuthSettings::class)->refresh();

        $this->assertTrue($fresh->microsoft_enabled);
        $this->assertSame('client-123', $fresh->microsoft_client_id);
        $this->assertSame('super-secret', $fresh->microsoft_client_secret);
        $this->assertSame('https://app.test/auth/microsoft/callback', $fresh->microsoft_redirect);
        $this->assertSame('tenant-abc', $fresh->microsoft_tenant);
    }

    public function test_client_secret_is_declared_encrypted(): void
    {
        $this->assertContains('microsoft_client_secret', AuthSettings::encrypted());
    }
}
