<?php

namespace Tests\Unit\Settings;

use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Support\ApplySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_smtp_settings_to_runtime_config(): void
    {
        $mail = app(MailSettings::class);
        $mail->default_mailer = 'smtp';
        $mail->from_address = 'noreply@example.test';
        $mail->from_name = 'Inventorix Test';
        $mail->smtp_host = 'mail.example.test';
        $mail->smtp_port = 587;
        $mail->smtp_scheme = 'smtps';
        $mail->smtp_username = 'user';
        $mail->smtp_password = 'secret';
        $mail->save();

        app(ApplySettings::class)();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
        $this->assertSame('Inventorix Test', config('mail.from.name'));
        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('user', config('mail.mailers.smtp.username'));
        $this->assertSame('secret', config('mail.mailers.smtp.password'));
    }

    public function test_it_applies_postal_settings_and_registers_the_mailer(): void
    {
        $mail = app(MailSettings::class);
        $mail->default_mailer = 'postal';
        $mail->postal_domain = 'https://postal.example.test';
        $mail->postal_key = 'postal-key';
        $mail->save();

        app(ApplySettings::class)();

        $this->assertSame('postal', config('mail.default'));
        $this->assertSame('postal', config('mail.mailers.postal.transport'));
        $this->assertSame('https://postal.example.test', config('postal.domain'));
        $this->assertSame('postal-key', config('postal.key'));
    }

    public function test_it_applies_general_app_name(): void
    {
        $general = app(GeneralSettings::class);
        $general->app_name = 'My Inventory';
        $general->save();

        app(ApplySettings::class)();

        $this->assertSame('My Inventory', config('app.name'));
    }

    public function test_it_applies_microsoft_azure_settings_to_runtime_config(): void
    {
        $auth = app(AuthSettings::class);
        $auth->microsoft_enabled = true;
        $auth->microsoft_client_id = 'client-123';
        $auth->microsoft_client_secret = 'super-secret';
        $auth->microsoft_redirect = 'https://app.test/auth/microsoft/callback';
        $auth->microsoft_tenant = 'tenant-abc';
        $auth->save();

        app(ApplySettings::class)();

        $this->assertTrue(config('services.microsoft-azure.enabled'));
        $this->assertSame('client-123', config('services.microsoft-azure.client_id'));
        $this->assertSame('super-secret', config('services.microsoft-azure.client_secret'));
        $this->assertSame('https://app.test/auth/microsoft/callback', config('services.microsoft-azure.redirect'));
        $this->assertSame('tenant-abc', config('services.microsoft-azure.tenant'));
    }
}
