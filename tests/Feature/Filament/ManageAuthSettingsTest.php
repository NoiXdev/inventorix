<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageAuthSettings;
use App\Models\User;
use App\Settings\AuthSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageAuthSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_microsoft_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAuthSettings::class)
            ->fillForm([
                'microsoft_enabled' => true,
                'microsoft_client_id' => 'client-123',
                'microsoft_client_secret' => 'super-secret',
                'microsoft_redirect' => 'https://app.test/auth/microsoft/callback',
                'microsoft_tenant' => 'tenant-abc',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(AuthSettings::class)->refresh();
        $this->assertTrue($settings->microsoft_enabled);
        $this->assertSame('client-123', $settings->microsoft_client_id);
        $this->assertSame('super-secret', $settings->microsoft_client_secret);
        $this->assertSame('tenant-abc', $settings->microsoft_tenant);
    }

    public function test_blank_client_secret_keeps_the_existing_value(): void
    {
        $existing = app(AuthSettings::class);
        $existing->microsoft_enabled = true;
        $existing->microsoft_client_id = 'client-123';
        $existing->microsoft_client_secret = 'original-secret';
        $existing->microsoft_redirect = 'https://app.test/auth/microsoft/callback';
        $existing->microsoft_tenant = 'tenant-abc';
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAuthSettings::class)
            ->fillForm([
                'microsoft_client_id' => 'client-changed',
                'microsoft_client_secret' => '', // left blank on purpose
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(AuthSettings::class)->refresh();
        $this->assertSame('client-changed', $settings->microsoft_client_id);
        $this->assertSame('original-secret', $settings->microsoft_client_secret);
    }

    public function test_client_secret_is_never_sent_to_the_browser(): void
    {
        $existing = app(AuthSettings::class);
        $existing->microsoft_enabled = true;
        $existing->microsoft_client_id = 'client-123';
        $existing->microsoft_client_secret = 'original-secret';
        $existing->microsoft_redirect = 'https://app.test/auth/microsoft/callback';
        $existing->microsoft_tenant = 'tenant-abc';
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAuthSettings::class)
            ->assertFormSet([
                'microsoft_client_secret' => null,
            ]);
    }
}
