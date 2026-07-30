<?php

namespace Tests\Feature\App;

use App\Models\User;
use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_never_exposes_secrets(): void
    {
        $mail = app(MailSettings::class);
        $mail->smtp_password = 'super-secret';
        $mail->ses_secret = 'ses-secret';
        $mail->postmark_token = 'pm-token';
        $mail->resend_key = 'resend-key';
        $mail->postal_key = 'postal-key';
        $mail->save();

        $storage = app(StorageSettings::class);
        $storage->secret = 'storage-secret';
        $storage->save();

        $auth = app(AuthSettings::class);
        $auth->microsoft_client_secret = 'ms-secret';
        $auth->save();

        $this->actingAs($this->actor())->get('/app/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('settings/index')
                ->has('settings.mail')
                // every encrypted field is nulled out before reaching the browser
                ->where('settings.mail.smtp_password', null)
                ->where('settings.mail.ses_secret', null)
                ->where('settings.mail.postmark_token', null)
                ->where('settings.mail.resend_key', null)
                ->where('settings.mail.postal_key', null)
                ->where('settings.storage.secret', null)
                ->where('settings.auth.microsoft_client_secret', null)
                ->has('t')
                ->has('options.mailDrivers')
                ->etc());

        // Defence in depth: no stored secret value should appear anywhere in the payload.
        $json = $this->actingAs($this->actor())->get('/app/settings')->content();
        foreach (['super-secret', 'ses-secret', 'pm-token', 'resend-key', 'postal-key', 'storage-secret', 'ms-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_update_general_persists_and_applies(): void
    {
        $this->actingAs($this->actor())
            ->put('/app/settings/general', ['app_name' => 'Inventorix X'])
            ->assertRedirect();

        $this->assertSame('Inventorix X', app(GeneralSettings::class)->refresh()->app_name);
        $this->assertSame('Inventorix X', config('app.name'));
    }

    public function test_update_mail_keeps_secret_when_blank_and_overwrites_when_present(): void
    {
        $mail = app(MailSettings::class);
        $mail->smtp_password = 'original';
        $mail->save();

        $base = [
            'default_mailer' => 'smtp', 'from_address' => 'a@b.de', 'from_name' => 'Ops',
            'use_path_style_endpoint' => false, // ignored by mail
        ];

        // blank -> keep
        $this->actingAs($this->actor())->put('/app/settings/mail', $base + ['smtp_password' => ''])->assertRedirect();
        $this->assertSame('original', app(MailSettings::class)->refresh()->smtp_password);

        // non-blank -> overwrite
        $this->actingAs($this->actor())->put('/app/settings/mail', $base + ['smtp_password' => 'changed'])->assertRedirect();
        $this->assertSame('changed', app(MailSettings::class)->refresh()->smtp_password);

        // save applied the settings to runtime config immediately
        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_update_mail_validation(): void
    {
        $this->actingAs($this->actor())
            ->put('/app/settings/mail', ['default_mailer' => 'nope', 'from_address' => 'not-email', 'from_name' => ''])
            ->assertSessionHasErrors(['default_mailer', 'from_address', 'from_name']);
    }

    public function test_update_storage_persists(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/storage', [
            'key' => 'AKIA', 'region' => 'eu', 'bucket' => 'files', 'use_path_style_endpoint' => true,
        ])->assertRedirect();

        $this->assertSame('files', app(StorageSettings::class)->refresh()->bucket);
        $this->assertSame('files', config('filesystems.disks.s3.bucket'));
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/settings')->assertRedirect();
    }
}
