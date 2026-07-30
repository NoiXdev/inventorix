<?php

namespace Tests\Feature\App;

use App\Mail\TestMail;
use App\Mail\WarrantyExpiryDigest;
use App\Models\Asset;
use App\Models\User;
use App\Settings\WarrantySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTestActionsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function mailPayload(array $overrides = []): array
    {
        return array_merge([
            'default_mailer' => 'log', 'from_address' => 'a@b.de', 'from_name' => 'Ops',
        ], $overrides);
    }

    public function test_update_auth_required_if_microsoft_enabled(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/auth', [
            'multi_factor_enabled' => false, 'multi_factor_force' => false, 'multi_factor_recoverable' => false,
            'microsoft_enabled' => true,
        ])->assertSessionHasErrors(['microsoft_client_id', 'microsoft_redirect', 'microsoft_tenant']);
    }

    public function test_update_warranty_normalizes_lead_days(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/warranty', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => ['7', '30', '7', '90'],
        ])->assertRedirect();

        $this->assertSame([90, 30, 7], app(WarrantySettings::class)->refresh()->lead_days);
    }

    public function test_mail_test_sends(): void
    {
        Mail::fake();
        $this->actingAs($this->actor())
            ->post('/app/settings/mail/test', $this->mailPayload(['email' => 'to@x.de']))
            ->assertRedirect();
        Mail::assertSent(TestMail::class);
    }

    public function test_storage_test_uses_probe(): void
    {
        Storage::fake('s3');
        $this->actingAs($this->actor())->post('/app/settings/storage/test', [
            'key' => 'k', 'secret' => 's', 'bucket' => 'b', 'use_path_style_endpoint' => false,
        ])->assertRedirect()->assertSessionHas('success');
    }

    public function test_warranty_test_warns_when_nothing_due(): void
    {
        $this->actingAs($this->actor())->post('/app/settings/warranty/test', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => [30],
        ])->assertRedirect()->assertSessionHas('warning');
    }

    public function test_warranty_test_sends_digest_when_due(): void
    {
        Mail::fake();
        // an asset whose guarantee ends in ~30 days -> due for the 30-day lead
        Asset::factory()->create(['guarantee_end' => now()->addDays(30)->format('Y-m-d')]);

        $this->actingAs($this->actor())->post('/app/settings/warranty/test', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => [30],
        ])->assertRedirect();
        Mail::assertSent(WarrantyExpiryDigest::class);
    }
}
