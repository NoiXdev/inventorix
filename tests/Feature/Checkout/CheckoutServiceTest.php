<?php

namespace Tests\Feature\Checkout;

use App\Enums\SystemStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CheckoutService
    {
        return app(CheckoutService::class);
    }

    private function available(array $attributes = []): Asset
    {
        return Asset::factory()->unassigned()->create([
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            ...$attributes,
        ]);
    }

    public function test_checkout_to_a_user_assigns_dates_and_counts(): void
    {
        $location = Location::factory()->create();
        $asset = $this->available(['location_id' => $location->id]);
        $user = User::factory()->withoutLogin()->create();
        $inUse = StatusLabel::system(SystemStatus::IN_USE)->id;

        $this->service()->checkOut($asset, $user, statusId: $inUse, expectedCheckin: now()->addWeek()->startOfDay(), note: 'For the trip');

        $asset->refresh();
        $this->assertTrue($asset->assignee->is($user));
        $this->assertSame($inUse, $asset->status_id);
        $this->assertSame(1, $asset->checkout_counter);
        $this->assertNotNull($asset->last_checkout);
        $this->assertSame(now()->addWeek()->toDateString(), $asset->expected_checkin->toDateString());
        // Users have no location: the asset stays where it is.
        $this->assertSame($location->id, $asset->location_id);

        $entry = Activity::query()->where('subject_id', $asset->id)->where('event', 'checked_out')->sole();
        $this->assertSame('For the trip', $entry->getProperty('note'));
        $this->assertSame(0, Activity::query()->where('subject_id', $asset->id)->where('description', 'assignee_changed')->count());
    }

    public function test_checkout_to_a_location_moves_the_asset_and_what_is_assigned_to_it(): void
    {
        $asset = $this->available();
        $monitor = Asset::factory()->assignedTo($asset)->create(['location_id' => null]);
        $office = Location::factory()->create();

        $this->service()->checkOut($asset, $office);

        $this->assertSame($office->id, $asset->fresh()->location_id);
        $this->assertSame($office->id, $monitor->fresh()->location_id);
    }

    public function test_checkout_to_an_asset_takes_its_location_else_its_default_location(): void
    {
        $here = Location::factory()->create();
        $default = Location::factory()->create();

        $dock = Asset::factory()->create(['location_id' => $here->id, 'rtd_location_id' => $default->id]);
        $asset = $this->available();
        $this->service()->checkOut($asset, $dock);
        $this->assertSame($here->id, $asset->fresh()->location_id);

        $dock2 = Asset::factory()->create(['location_id' => null, 'rtd_location_id' => $default->id]);
        $asset2 = $this->available();
        $this->service()->checkOut($asset2, $dock2);
        $this->assertSame($default->id, $asset2->fresh()->location_id);
    }

    public function test_an_assigned_or_undeployable_or_deleted_asset_cannot_be_checked_out(): void
    {
        $assigned = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);
        $defect = $this->available(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);
        $deleted = $this->available();
        $deleted->delete();

        foreach ([$assigned, $defect, $deleted] as $asset) {
            $this->assertNotNull($this->service()->checkoutBlocker($asset));
            try {
                $this->service()->checkOut($asset, User::factory()->withoutLogin()->create());
                $this->fail('Expected CheckoutNotAllowedException');
            } catch (CheckoutNotAllowedException) {
            }
        }
        $this->assertNull($this->service()->checkoutBlocker($this->available()));
    }

    public function test_targets_that_would_loop_or_are_deleted_are_refused(): void
    {
        $asset = $this->available();
        $child = Asset::factory()->assignedTo($asset)->create();
        $gone = Location::factory()->create();
        $gone->delete();

        foreach ([$asset, $child, $gone] as $target) {
            try {
                $this->service()->checkOut($asset, $target);
                $this->fail('Expected CheckoutNotAllowedException');
            } catch (CheckoutNotAllowedException) {
            }
        }
        $this->assertNull($asset->fresh()->assignee_id);
        $this->assertSame(0, $asset->fresh()->checkout_counter);
    }

    public function test_checkout_status_must_be_deployable(): void
    {
        $this->expectException(CheckoutNotAllowedException::class);

        $this->service()->checkOut($this->available(), User::factory()->withoutLogin()->create(),
            statusId: StatusLabel::system(SystemStatus::DEFECT)->id);
    }

    public function test_checkin_unassigns_and_returns_to_the_given_or_default_location(): void
    {
        $now = Location::factory()->create();
        $default = Location::factory()->create();
        $storage = StatusLabel::system(SystemStatus::STORAGE)->id;

        $asset = Asset::factory()->create([
            'location_id' => $now->id, 'rtd_location_id' => $default->id,
            'expected_checkin' => now()->addDay(), 'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
        ]);
        $this->service()->checkIn($asset, statusId: $storage, note: 'Back');

        $asset->refresh();
        $this->assertNull($asset->assignee_type);
        $this->assertNull($asset->assignee_id);
        $this->assertNull($asset->expected_checkin);
        $this->assertSame($storage, $asset->status_id);
        $this->assertSame($default->id, $asset->location_id);
        $this->assertSame(1, $asset->checkin_counter);
        $this->assertNotNull($asset->last_checkin);
        $this->assertSame('Back', Activity::query()->where('subject_id', $asset->id)->where('event', 'checked_in')->sole()->getProperty('note'));

        // An explicit location wins; without one and without a default, it stays.
        $elsewhere = Location::factory()->create();
        $other = Asset::factory()->create(['location_id' => $now->id, 'rtd_location_id' => $default->id]);
        $this->service()->checkIn($other, locationId: $elsewhere->id);
        $this->assertSame($elsewhere->id, $other->fresh()->location_id);

        $noDefault = Asset::factory()->create(['location_id' => $now->id, 'rtd_location_id' => null]);
        $this->service()->checkIn($noDefault);
        $this->assertSame($now->id, $noDefault->fresh()->location_id);
    }

    public function test_the_passed_asset_reflects_the_checkout(): void
    {
        $asset = $this->available();
        $user = User::factory()->withoutLogin()->create();

        $this->service()->checkOut($asset, $user);

        $this->assertSame($user->id, $asset->assignee_id);
        $this->assertFalse($asset->isDirty());
    }
}
