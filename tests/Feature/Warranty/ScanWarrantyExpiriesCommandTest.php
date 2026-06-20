<?php

namespace Tests\Feature\Warranty;

use App\Mail\WarrantyExpiryDigest;
use App\Models\Asset;
use App\Models\WarrantyNotification;
use App\Settings\WarrantySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScanWarrantyExpiriesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configure(bool $enabled, array $recipients = ['ops@example.de']): void
    {
        $settings = app(WarrantySettings::class);
        $settings->enabled = $enabled;
        $settings->recipients = $recipients;
        $settings->lead_days = [90, 30, 7];
        $settings->save();
    }

    public function test_it_sends_digest_and_writes_ledger(): void
    {
        Mail::fake();
        $this->configure(enabled: true);
        Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);
        Asset::factory()->create(['guarantee_end' => now()->subDay()->toDateString()]);

        $this->artisan('warranty:scan-expiries')->assertExitCode(0);

        Mail::assertSent(WarrantyExpiryDigest::class, fn (WarrantyExpiryDigest $m): bool => $m->hasTo('ops@example.de'));
        $this->assertSame(2, WarrantyNotification::count());
    }

    public function test_second_run_is_idempotent(): void
    {
        Mail::fake();
        $this->configure(enabled: true);
        Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);

        $this->artisan('warranty:scan-expiries')->assertExitCode(0);
        Mail::assertSentCount(1);

        $this->artisan('warranty:scan-expiries')->assertExitCode(0);
        Mail::assertSentCount(1); // no new mail
        $this->assertSame(1, WarrantyNotification::count());
    }

    public function test_it_no_ops_when_disabled(): void
    {
        Mail::fake();
        $this->configure(enabled: false);
        Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);

        $this->artisan('warranty:scan-expiries')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, WarrantyNotification::count());
    }

    public function test_it_no_ops_when_no_recipients(): void
    {
        Mail::fake();
        $this->configure(enabled: true, recipients: []);
        Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);

        $this->artisan('warranty:scan-expiries')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, WarrantyNotification::count());
    }

    public function test_mail_failure_leaves_ledger_unwritten(): void
    {
        $this->configure(enabled: true);
        Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $this->artisan('warranty:scan-expiries')->assertExitCode(1);

        $this->assertSame(0, WarrantyNotification::count());
    }
}
