<?php

namespace Tests\Feature\Alerts;

use App\Mail\AlertsDigest;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use App\Services\AlertService;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OffboardingDueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-28 10:00:00');
        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->save();
    }

    private function leaving(string $endDate): User
    {
        return User::factory()->create(['end_date' => $endDate]);
    }

    private function holdAsset(User $user): void
    {
        Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => $user->id]);
    }

    private function holdSeat(User $user): void
    {
        $license = License::factory()->seats(1)->create();
        $license->licenseSeats()->first()->forceFill(['assigned_user_id' => $user->id])->save();
    }

    private function holdAccessory(User $user): void
    {
        AccessoryCheckout::forceCreate([
            'accessory_id' => Accessory::factory()->create()->id,
            'assigned_type' => User::class, 'assigned_id' => $user->id, 'checked_out_at' => now(),
        ]);
    }

    public function test_default_is_14_days(): void
    {
        $this->assertSame(14, app(AlertSettings::class)->offboarding_warning_days);
    }

    public function test_user_leaving_soon_with_an_asset_is_listed_with_counts_and_url(): void
    {
        $user = $this->leaving('2026-12-03');
        $this->holdAsset($user);
        $this->holdSeat($user);
        $this->holdAccessory($user);

        $section = app(AlertService::class)->licenseSections()['offboarding_due'];

        $this->assertCount(1, $section);
        $row = $section->first();
        $this->assertTrue($row->is($user));
        $this->assertSame(1, (int) $row->assets_count);
        $this->assertSame(1, (int) $row->seats_count);
        $this->assertSame(1, (int) $row->accessories_count);
    }

    public function test_user_leaving_beyond_the_window_is_not_listed(): void
    {
        $user = $this->leaving('2026-12-28');
        $this->holdAsset($user);

        $this->assertArrayNotHasKey('offboarding_due', app(AlertService::class)->licenseSections());
    }

    public function test_user_whose_end_date_passed_and_still_holds_a_seat_is_listed(): void
    {
        $user = $this->leaving('2026-11-01');
        $this->holdSeat($user);

        $this->assertCount(1, app(AlertService::class)->licenseSections()['offboarding_due']);
    }

    public function test_user_holding_nothing_is_not_listed(): void
    {
        $this->leaving('2026-11-29');

        $this->assertArrayNotHasKey('offboarding_due', app(AlertService::class)->licenseSections());
    }

    public function test_window_follows_the_setting_and_rows_are_ordered_by_end_date(): void
    {
        $settings = app(AlertSettings::class);
        $settings->offboarding_warning_days = 40;
        $settings->save();
        $later = $this->leaving('2026-12-20');
        $sooner = $this->leaving('2026-12-01');
        $this->holdAsset($later);
        $this->holdAsset($sooner);

        $ids = app(AlertService::class)->licenseSections()['offboarding_due']->pluck('id')->all();

        $this->assertSame([$sooner->id, $later->id], $ids);
    }

    public function test_a_digest_with_only_this_section_is_sent(): void
    {
        Mail::fake();
        $user = $this->leaving('2026-12-03');
        $this->holdAsset($user);

        $this->assertSame(1, app(AlertService::class)->sendDigest());

        Mail::assertSent(AlertsDigest::class, function (AlertsDigest $mail) use ($user) {
            $html = $mail->render();

            return str_contains($html, route('app.users.offboard', $user)) && str_contains($html, $user->name);
        });
    }

    public function test_setting_is_validated_and_saved(): void
    {
        $payload = [
            'enabled' => true, 'recipients' => ['a@example.test'], 'audit_interval_months' => 12,
            'audit_warning_days' => 14, 'checkin_warning_days' => 7, 'license_warning_days' => 30,
        ];
        $admin = User::factory()->superuser()->create();

        $this->actingAs($admin)->put('/app/settings/alerts', [...$payload, 'offboarding_warning_days' => 366])
            ->assertSessionHasErrors('offboarding_warning_days');
        $this->actingAs($admin)->put('/app/settings/alerts', [...$payload, 'offboarding_warning_days' => 21])
            ->assertSessionHasNoErrors();

        $this->assertSame(21, app(AlertSettings::class)->refresh()->offboarding_warning_days);
    }
}
