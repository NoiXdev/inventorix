<?php

namespace Tests\Feature\Audit;

use App\Mail\AlertsDigest;
use App\Mail\CheckinReminder;
use App\Models\Asset;
use App\Models\License;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\LicenseService;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DueAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Asset $auditOverdue;

    private Asset $auditDue;

    private Asset $checkinOverdue;

    private Asset $checkinDue;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-28 10:00:00');

        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->audit_warning_days = 14;
        $settings->checkin_warning_days = 7;
        $settings->save();

        $this->auditOverdue = Asset::factory()->unassigned()->create(['next_audit_date' => '2026-11-27']);
        $this->auditDue = Asset::factory()->unassigned()->create(['next_audit_date' => '2026-12-12']);
        Asset::factory()->unassigned()->create(['next_audit_date' => '2026-12-13']); // outside the window
        $this->checkinOverdue = Asset::factory()->create(['expected_checkin' => '2026-11-20']);
        $this->checkinDue = Asset::factory()->create(['expected_checkin' => '2026-12-05']);
        Asset::factory()->create(['expected_checkin' => '2026-12-20']); // outside the window
        // Not checked out any more: never due back.
        Asset::factory()->unassigned()->create(['expected_checkin' => '2026-11-01']);
    }

    /** @return list<string> */
    private function listed(string $due): array
    {
        $ids = [];
        $this->actingAs(User::factory()->superuser()->create())->get("/app/assets?filter[due]={$due}")
            ->assertInertia(function (Assert $p) use (&$ids) {
                $ids = array_column($p->toArray()['props']['assets']['data'], 'id');
            });
        sort($ids);

        return $ids;
    }

    public function test_the_asset_list_filters_due_and_overdue(): void
    {
        $this->assertSame([$this->auditOverdue->id], $this->listed('audit_overdue'));
        $this->assertSame([$this->auditDue->id], $this->listed('audit_due'));
        $this->assertSame([$this->checkinOverdue->id], $this->listed('checkin_overdue'));
        $this->assertSame([$this->checkinDue->id], $this->listed('checkin_due'));
        $this->assertCount(Asset::count(), $this->listed('bogus'));
    }

    public function test_the_dashboard_counts_them(): void
    {
        $this->actingAs(User::factory()->withPermissions(['assets.view' => 1])->create())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->where('due', [
                'audit_due' => 1, 'audit_overdue' => 1, 'checkin_due' => 1, 'checkin_overdue' => 1,
            ]));
        $this->actingAs(User::factory()->create())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->missing('due'));
    }

    public function test_reminders_go_to_each_assignee_once_per_expected_date(): void
    {
        Mail::fake();

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(CheckinReminder::class, 2);
        Mail::assertSent(CheckinReminder::class, fn (CheckinReminder $m) => $m->hasTo($this->checkinOverdue->assignee->email)
            && $m->assets->pluck('id')->all() === [$this->checkinOverdue->id]);
        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->hasTo('it@example.test')
            && array_keys($m->sections) === ['checkin_overdue', 'checkin_due', 'audit_overdue', 'audit_due']);

        // The next day: no second reminder for the same dates, but the overview again.
        Mail::fake();
        Carbon::setTestNow('2026-11-29 10:00:00');
        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertNotSent(CheckinReminder::class);
        Mail::assertSent(AlertsDigest::class, 1);

        // A new expected date is a new reminder.
        Mail::fake();
        $this->checkinOverdue->update(['expected_checkin' => '2026-11-28']);
        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertSent(CheckinReminder::class, 1);
    }

    public function test_assignees_without_email_are_skipped_and_one_mail_bundles_a_users_assets(): void
    {
        Mail::fake();
        $this->checkinDue->assignee->forceFill(['email' => null])->save();
        Asset::factory()->assignedTo($this->checkinOverdue->assignee)->create(['expected_checkin' => '2026-11-30']);

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(CheckinReminder::class, 1);
        Mail::assertSent(CheckinReminder::class, fn (CheckinReminder $m) => $m->assets->count() === 2);
    }

    public function test_disabled_alerts_send_nothing(): void
    {
        Mail::fake();
        $settings = app(AlertSettings::class);
        $settings->enabled = false;
        $settings->save();

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_settings_are_saved_by_superusers_only(): void
    {
        $payload = [
            'enabled' => true, 'recipients' => ['a@example.test'],
            'audit_interval_months' => 24, 'audit_warning_days' => 30, 'checkin_warning_days' => 3, 'license_warning_days' => 30,
        ];

        $this->actingAs(User::factory()->withPermissions(['admin' => 1])->create())
            ->put('/app/settings/alerts', $payload)->assertForbidden();

        $this->actingAs(User::factory()->superuser()->create())
            ->put('/app/settings/alerts', $payload)->assertSessionHasNoErrors();
        $settings = app(AlertSettings::class)->refresh();
        $this->assertSame(24, $settings->audit_interval_months);
        $this->assertSame(['a@example.test'], $settings->recipients);

        $this->actingAs(User::factory()->superuser()->create())
            ->put('/app/settings/alerts', [...$payload, 'audit_interval_months' => -1, 'recipients' => ['nope']])
            ->assertSessionHasErrors(['audit_interval_months', 'recipients.0']);
    }

    public function test_the_test_button_sends_the_overview_now(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->superuser()->create())->post('/app/settings/alerts/test', [
            'enabled' => true, 'recipients' => ['it@example.test'],
            'audit_interval_months' => 12, 'audit_warning_days' => 14, 'checkin_warning_days' => 7, 'license_warning_days' => 30,
        ])->assertSessionHas('success');

        Mail::assertSent(AlertsDigest::class, 1);
        Mail::assertNotSent(CheckinReminder::class);
    }

    public function test_archived_assets_are_never_due(): void
    {
        $this->auditOverdue->update(['archived' => true]);
        $archivedStatus = StatusLabel::factory()->create(['archived' => true, 'deployable' => false, 'pending' => false]);
        $this->checkinOverdue->update(['status_id' => $archivedStatus->id]);

        $this->assertSame([], $this->listed('audit_overdue'));
        $this->assertSame([], $this->listed('checkin_overdue'));
    }

    public function test_a_failed_reminder_does_not_stop_the_others_or_the_overview(): void
    {
        $this->checkinOverdue->assignee->forceFill(['email' => 'bad@example.test'])->save();
        $sent = [];
        Mail::shouldReceive('to')->andReturnUsing(function ($to) use (&$sent) {
            $pending = \Mockery::mock();
            $pending->shouldReceive('send')->andReturnUsing(function ($mailable) use ($to, &$sent) {
                if ($to === 'bad@example.test') {
                    throw new \RuntimeException('550 mailbox unavailable');
                }
                $sent[] = [is_array($to) ? implode(',', $to) : $to, $mailable::class];
            });

            return $pending;
        });

        $this->artisan('alerts:send')->assertSuccessful();

        $this->assertContains([$this->checkinDue->assignee->email, CheckinReminder::class], $sent);
        $this->assertContains(['it@example.test', AlertsDigest::class], $sent);
        // The failed one is not recorded, so it is tried again tomorrow.
        $this->assertSame(0, \App\Models\CheckinReminder::query()->where('asset_id', $this->checkinOverdue->id)->count());
    }

    public function test_a_new_assignee_is_reminded_even_for_the_same_date(): void
    {
        Mail::fake();
        $this->artisan('alerts:send')->assertSuccessful();

        Mail::fake();
        $newUser = User::factory()->withoutLogin()->create();
        $this->checkinOverdue->forceFill(['assignee_type' => User::class, 'assignee_id' => $newUser->id])->save();
        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(CheckinReminder::class, 1);
        Mail::assertSent(CheckinReminder::class, fn (CheckinReminder $m) => $m->hasTo($newUser->email));
    }

    public function test_the_overview_lists_expiring_licenses_and_licenses_with_few_free_seats(): void
    {
        Mail::fake();
        $expiring = License::factory()->create(['name' => 'Expiring', 'expiration_date' => '2026-12-20']);
        $terminated = License::factory()->create(['expiration_date' => '2026-12-01', 'termination_date' => '2026-11-01']);
        License::factory()->create(['expiration_date' => '2027-06-01']); // outside the 30 days
        $low = License::factory()->seats(2)->create(['name' => 'Low', 'min_amt' => 2]);
        app(LicenseService::class)->checkOut($low, User::factory()->create());

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->licenseSections['license_expiring']->pluck('id')->all() === [$expiring->id]
            && $m->licenseSections['license_low']->pluck('id')->all() === [$low->id]
            && ! in_array($terminated->id, $m->licenseSections['license_expiring']->pluck('id')->all(), true));
    }
}
