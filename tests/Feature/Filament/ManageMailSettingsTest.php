<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageMailSettings;
use App\Models\User;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_smtp_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageMailSettings::class)
            ->fillForm([
                'default_mailer' => 'smtp',
                'from_address' => 'noreply@example.test',
                'from_name' => 'Inventorix',
                'smtp_host' => 'mail.example.test',
                'smtp_port' => 587,
                'smtp_scheme' => 'tls',
                'smtp_username' => 'user',
                'smtp_password' => 'secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(MailSettings::class)->refresh();
        $this->assertSame('smtp', $settings->default_mailer);
        $this->assertSame('mail.example.test', $settings->smtp_host);
        $this->assertSame('secret', $settings->smtp_password);
    }

    public function test_blank_secret_keeps_the_existing_value(): void
    {
        $existing = app(MailSettings::class);
        $existing->default_mailer = 'smtp';
        $existing->from_address = 'noreply@example.test';
        $existing->from_name = 'Inventorix';
        $existing->smtp_host = 'mail.example.test';
        $existing->smtp_port = 587;
        $existing->smtp_password = 'original-secret';
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageMailSettings::class)
            ->fillForm([
                'from_name' => 'Changed Name',
                'smtp_password' => '', // left blank on purpose
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(MailSettings::class)->refresh();
        $this->assertSame('Changed Name', $settings->from_name);
        $this->assertSame('original-secret', $settings->smtp_password);
    }
}
