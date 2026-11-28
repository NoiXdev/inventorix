<?php

namespace Tests\Feature\Users;

use App\Enums\SystemStatus;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\Department;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\AccessoryService;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OffboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $leaver;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->leaver = User::factory()->create(['name' => 'Lea Leaver']);
    }

    /** @param array<string, int> $extra */
    private function actor(array $extra = []): User
    {
        return User::factory()->withPermissions(array_merge([
            'users.view' => 1, 'users.edit' => 1,
            'assets.view' => 1, 'assets.checkin' => 1, 'assets.checkout' => 1,
            'licenses.view' => 1, 'licenses.checkin' => 1, 'licenses.checkout' => 1,
            'accessories.view' => 1, 'accessories.checkin' => 1, 'accessories.checkout' => 1,
            'consumables.view' => 1,
        ], $extra))->create();
    }

    private function asset(array $attributes = []): Asset
    {
        return Asset::factory()->assignedTo($this->leaver)->inStatus(SystemStatus::IN_USE)->create($attributes);
    }

    private function seat(): LicenseSeat
    {
        $license = License::factory()->seats(2)->create(['name' => 'Office']);

        return app(LicenseService::class)->checkOut($license, $this->leaver);
    }

    private function accessoryCheckout(): AccessoryCheckout
    {
        $accessory = Accessory::factory()->create(['name' => 'Headset', 'qty' => 3]);
        app(AccessoryService::class)->checkOut($accessory, $this->leaver);

        return AccessoryCheckout::query()->where('accessory_id', $accessory->id)->firstOrFail();
    }

    private function url(): string
    {
        return "/app/users/{$this->leaver->id}/offboard";
    }

    public function test_checks_in_an_asset_a_seat_and_an_accessory_in_one_request(): void
    {
        $asset = $this->asset();
        $seat = $this->seat();
        $checkout = $this->accessoryCheckout();
        $storage = StatusLabel::system(SystemStatus::STORAGE);
        $location = Location::factory()->create();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [
                ['type' => 'asset', 'id' => $asset->id, 'action' => 'checkin'],
                ['type' => 'license_seat', 'id' => $seat->id, 'action' => 'checkin'],
                ['type' => 'accessory_checkout', 'id' => $checkout->id, 'action' => 'checkin'],
            ],
            'status_id' => $storage->id,
            'location_id' => $location->id,
            'note' => 'Last day',
        ])->assertRedirect("/app/users/{$this->leaver->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Offboarding done: 3 checked in, 0 transferred.');

        $asset->refresh();
        $this->assertNull($asset->assignee_id);
        $this->assertSame($storage->id, $asset->status_id);
        $this->assertSame($location->id, $asset->location_id);
        $this->assertNull($seat->refresh()->assigned_user_id);
        $this->assertNull(AccessoryCheckout::query()->find($checkout->id));

        $notes = Activity::query()->where('event', 'checked_in')->get()->map(fn (Activity $a) => $a->getProperty('note'));
        $this->assertCount(3, $notes);
        foreach ($notes as $note) {
            $this->assertSame('Offboarding of Lea Leaver: Last day', $note);
        }
    }

    public function test_transfers_all_three_to_another_user(): void
    {
        $asset = $this->asset();
        $seat = $this->seat();
        $checkout = $this->accessoryCheckout();
        $heir = User::factory()->create();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [
                ['type' => 'asset', 'id' => $asset->id, 'action' => 'transfer'],
                ['type' => 'license_seat', 'id' => $seat->id, 'action' => 'transfer'],
                ['type' => 'accessory_checkout', 'id' => $checkout->id, 'action' => 'transfer'],
            ],
            'transfer_to' => $heir->id,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Offboarding done: 0 checked in, 3 transferred.');

        $asset->refresh();
        $this->assertSame(User::class, $asset->assignee_type);
        $this->assertSame($heir->id, $asset->assignee_id);
        $this->assertSame(1, LicenseSeat::query()->where('license_id', $seat->license_id)->where('assigned_user_id', $heir->id)->count());
        $this->assertSame(0, LicenseSeat::query()->where('assigned_user_id', $this->leaver->id)->count());
        $this->assertSame(1, AccessoryCheckout::query()->where('assigned_type', User::class)->where('assigned_id', $heir->id)->count());
        $this->assertSame(0, AccessoryCheckout::query()->where('assigned_id', $this->leaver->id)->count());
        $this->assertSame('Offboarding of Lea Leaver', Activity::query()->where('subject_id', $asset->id)->where('event', 'checked_out')->latest('id')->first()->getProperty('note'));
    }

    public function test_an_item_no_longer_held_fails_the_whole_request(): void
    {
        $asset = $this->asset();
        $gone = $this->asset(['name' => 'Gone laptop']);
        $seat = $this->seat();
        $other = User::factory()->create();
        $gone->forceFill(['assignee_id' => $other->id])->save();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [
                ['type' => 'asset', 'id' => $asset->id, 'action' => 'checkin'],
                ['type' => 'license_seat', 'id' => $seat->id, 'action' => 'checkin'],
                ['type' => 'asset', 'id' => $gone->id, 'action' => 'checkin'],
            ],
        ])->assertSessionHasErrors(['items' => 'Gone laptop is no longer checked out to Lea Leaver.']);

        $this->assertSame($this->leaver->id, $asset->refresh()->assignee_id);
        $this->assertSame($this->leaver->id, $seat->refresh()->assigned_user_id);
        $this->assertSame($other->id, $gone->refresh()->assignee_id);
    }

    public function test_a_failing_transfer_rolls_back_the_other_items(): void
    {
        $broken = $this->asset(['name' => 'Broken phone', 'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);
        $seat = $this->seat();
        $heir = User::factory()->create();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [
                ['type' => 'license_seat', 'id' => $seat->id, 'action' => 'checkin'],
                ['type' => 'asset', 'id' => $broken->id, 'action' => 'transfer'],
            ],
            'transfer_to' => $heir->id,
        ])->assertSessionHasErrors('items');

        $this->assertStringContainsString('Broken phone', session('errors')->first('items'));
        $this->assertSame($this->leaver->id, $broken->refresh()->assignee_id);
        $this->assertSame($this->leaver->id, $seat->refresh()->assigned_user_id);
    }

    public function test_a_transfer_needs_the_checkout_permission_too(): void
    {
        $asset = $this->asset();
        $seat = $this->seat();
        $heir = User::factory()->create();

        $this->actingAs($this->actor(['assets.checkout' => -1]))->post($this->url(), [
            'items' => [
                ['type' => 'license_seat', 'id' => $seat->id, 'action' => 'checkin'],
                ['type' => 'asset', 'id' => $asset->id, 'action' => 'transfer'],
            ],
            'transfer_to' => $heir->id,
        ])->assertSessionHasErrors('items');

        $this->assertSame($this->leaver->id, $asset->refresh()->assignee_id);
        $this->assertSame($this->leaver->id, $seat->refresh()->assigned_user_id);
    }

    public function test_transfer_needs_a_different_user(): void
    {
        $asset = $this->asset();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [['type' => 'asset', 'id' => $asset->id, 'action' => 'transfer']],
        ])->assertSessionHasErrors('transfer_to');

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [['type' => 'asset', 'id' => $asset->id, 'action' => 'transfer']],
            'transfer_to' => $this->leaver->id,
        ])->assertSessionHasErrors('transfer_to');
    }

    public function test_disable_login_turns_login_off(): void
    {
        $asset = $this->asset();

        $this->actingAs($this->actor())->post($this->url(), [
            'items' => [['type' => 'asset', 'id' => $asset->id, 'action' => 'checkin']],
            'disable_login' => true,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->leaver->refresh()->login_enabled);
        $this->assertNull($asset->refresh()->assignee_id);
    }

    public function test_disable_login_alone_needs_no_items(): void
    {
        $this->actingAs($this->actor())->post($this->url(), ['items' => [], 'disable_login' => true])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->leaver->refresh()->login_enabled);

        $this->actingAs($this->actor())->post($this->url(), ['items' => []])->assertSessionHasErrors('items');
    }

    public function test_disable_login_is_refused_for_yourself(): void
    {
        $me = $this->actor(['assets.checkout' => 1]);

        $this->actingAs($me)->post("/app/users/{$me->id}/offboard", ['items' => [], 'disable_login' => true])
            ->assertSessionHasErrors('disable_login');

        $this->assertTrue($me->refresh()->login_enabled);
    }

    public function test_disable_login_is_refused_for_the_last_active_superuser(): void
    {
        $actor = User::factory()->superuser()->create(['login_enabled' => false]);
        $last = User::factory()->superuser()->create(['login_enabled' => true]);
        $asset = Asset::factory()->assignedTo($last)->create();

        $this->actingAs($actor)->post("/app/users/{$last->id}/offboard", [
            'items' => [['type' => 'asset', 'id' => $asset->id, 'action' => 'checkin']],
            'disable_login' => true,
        ])->assertSessionHasErrors(['disable_login' => 'At least one active superuser must remain.']);

        $this->assertTrue($last->refresh()->login_enabled);
        $this->assertSame($last->id, $asset->refresh()->assignee_id);
    }

    public function test_disable_login_needs_users_edit(): void
    {
        $this->actingAs($this->actor(['users.edit' => -1]))->post($this->url(), ['items' => [], 'disable_login' => true])
            ->assertSessionHasErrors('disable_login');

        $this->assertTrue($this->leaver->refresh()->login_enabled);
    }

    public function test_without_any_checkin_permission_the_page_is_forbidden(): void
    {
        $actor = User::factory()->withPermissions(['users.view' => 1, 'assets.view' => 1, 'assets.checkout' => 1])->create();

        $this->actingAs($actor)->get($this->url())->assertForbidden();
        $this->actingAs($actor)->post($this->url(), ['items' => [], 'disable_login' => true])->assertForbidden();
    }

    public function test_the_page_lists_what_the_user_holds_and_nothing_else(): void
    {
        $this->leaver->update(['department_id' => Department::factory()->create(['name' => 'Finance'])->id]);
        $asset = $this->asset(['name' => 'Leaver laptop']);
        $seat = $this->seat();
        $checkout = $this->accessoryCheckout();
        $consumable = Consumable::factory()->create(['name' => 'Toner']);
        ConsumableAssignment::query()->forceCreate(['consumable_id' => $consumable->id, 'user_id' => $this->leaver->id, 'qty' => 2, 'checked_out_at' => now()]);

        $someoneElse = User::factory()->create();
        Asset::factory()->assignedTo($someoneElse)->create();
        app(LicenseService::class)->checkOut($seat->license, $someoneElse);
        app(AccessoryService::class)->checkOut($checkout->accessory, $someoneElse);

        $this->actingAs($this->actor())->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/offboard')
                ->where('user.name', 'Lea Leaver')
                ->where('user.department_name', 'Finance')
                ->has('assets', 1)
                ->where('assets.0.id', $asset->id)
                ->has('licenses', 1)
                ->where('licenses.0.seat_id', $seat->id)
                ->has('accessories', 1)
                ->where('accessories.0.checkout_id', $checkout->id)
                ->has('consumables', 1)
                ->where('consumables.0.name', 'Toner')
                ->where('abilities.assets.checkin', true)
                ->where('abilities.assets.transfer', true)
                ->where('canDisableLogin', true)
                ->where('transferOptions', fn ($options) => collect($options)->doesntContain('value', $this->leaver->id))
            );
    }

    public function test_show_page_offers_offboarding_only_with_a_checkin_permission(): void
    {
        $this->actingAs($this->actor())->get("/app/users/{$this->leaver->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canOffboard', true));

        $viewer = User::factory()->withPermissions(['users.view' => 1])->create();
        $this->actingAs($viewer)->get("/app/users/{$this->leaver->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canOffboard', false));
    }
}
