<?php

namespace Tests\Unit\Settings;

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
        $mail->smtp_scheme = 'tls';
        $mail->smtp_username = 'user';
        $mail->smtp_password = 'secret';
        $mail->save();

        app(ApplySettings::class)();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
        $this->assertSame('Inventorix Test', config('mail.from.name'));
        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('tls', config('mail.mailers.smtp.scheme'));
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
}
