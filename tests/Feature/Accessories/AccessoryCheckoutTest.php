<?php

namespace Tests\Feature\Accessories;

use App\Exceptions\AccessoryCheckoutException;
use App\Mail\AlertsDigest;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Services\AccessoryService;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessoryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AccessoryService
    {
        return app(AccessoryService::class);
    }

    private function actor(): User
    {
        return User::factory()->withPermissions([
            'accessories.view' => 1, 'accessories.checkout' => 1, 'accessories.checkin' => 1, 'accessories.edit' => 1, 'accessories.delete' => 1,
        ])->create();
    }

    public function test_check_out_several_units_to_users_locations_and_assets(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 5]);
        $user = User::factory()->create(['name' => 'Ada']);

        $this->service()->checkOut($accessory, $user, 2, 'for the new desk');
        $this->service()->checkOut($accessory, Location::factory()->create());
        $this->service()->checkOut($accessory, Asset::factory()->create());

        $this->assertSame(1, $accessory->refresh()->remaining());
        $this->assertSame(2, AccessoryCheckout::query()->where('assigned_id', $user->id)->count());
        $entry = Activity::query()->where('subject_id', $accessory->id)->where('event', 'checked_out')->oldest('id')->first();
        $this->assertSame('User: Ada (×2)', $entry->getProperty('target'));
        $this->assertSame('for the new desk', $entry->getProperty('note'));
    }

    public function test_never_more_than_is_left_and_never_when_archived(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 2]);

        try {
            $this->service()->checkOut($accessory, User::factory()->create(), 3);
            $this->fail('Over-allocation was allowed.');
        } catch (AccessoryCheckoutException $e) {
            $this->assertSame('Only 2 of this accessory are left.', $e->getMessage());
        }
        $this->assertSame(0, $accessory->checkouts()->count());

        $accessory->update(['archived' => true]);
        $this->expectExceptionMessage('An archived accessory cannot be checked out.');
        $this->service()->checkOut($accessory, User::factory()->create());
    }

    public function test_check_in_through_the_routes(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 3]);
        $user = User::factory()->create();

        $this->actingAs($this->actor())->post("/app/accessories/{$accessory->id}/checkout", [
            'assignee_type' => 'user', 'assignee_id' => $user->id, 'qty' => 2, 'note' => '',
        ])->assertSessionHas('success', '2 checked out.');
        $this->actingAs($this->actor())->post("/app/accessories/{$accessory->id}/checkout", [
            'assignee_type' => 'user', 'assignee_id' => $user->id, 'qty' => 5,
        ])->assertSessionHasErrors('qty');

        $checkout = $accessory->checkouts()->first();
        $this->actingAs($this->actor())->post("/app/accessory-checkouts/{$checkout->id}/checkin", ['note' => 'back'])
            ->assertSessionHas('success');
        $this->assertSame(2, $accessory->refresh()->remaining());
        $this->assertSame('back', Activity::query()->where('subject_id', $accessory->id)->where('event', 'checked_in')->sole()->getProperty('note'));
    }

    public function test_quantity_cannot_drop_below_what_is_checked_out_and_deleting_needs_everything_back(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 3, 'name' => 'Dock']);
        $this->service()->checkOut($accessory, User::factory()->create(), 2);

        $this->actingAs($this->actor())->put("/app/accessories/{$accessory->id}", ['name' => 'Dock', 'qty' => 1])
            ->assertSessionHasErrors(['qty' => 'The quantity cannot be lower than the 2 units that are checked out.']);
        $this->actingAs($this->actor())->delete("/app/accessories/{$accessory->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($accessory);
    }

    public function test_low_stock_counts_what_is_left(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 5, 'min_amt' => 2]);
        $this->assertFalse($accessory->isLowStock());

        $this->service()->checkOut($accessory, User::factory()->create(), 3);
        $this->assertTrue($accessory->refresh()->isLowStock());
    }

    public function test_show_page_and_holder_pages(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 4, 'name' => 'Headset']);
        $user = User::factory()->create(['name' => 'Ada']);
        $asset = Asset::factory()->create();
        $this->service()->checkOut($accessory, $user, 2);
        $this->service()->checkOut($accessory, $asset);
        $viewer = User::factory()->withPermissions(['accessories.view' => 1, 'users.view' => 1, 'assets.view' => 1])->create();

        $this->actingAs($viewer)->get("/app/accessories/{$accessory->id}")
            ->assertInertia(fn (Assert $p) => $p->component('accessories/show')
                ->where('accessory.remaining', 1)
                ->has('checkouts', 3)
                ->where('checkouts.0.holder.type', 'user')
                ->where('checkouts.0.holder.name', 'Ada')
                ->reloadOnly('checkoutOptions', fn (Assert $r) => $r->where('checkoutOptions', null)));
        $this->actingAs($viewer)->get("/app/users/{$user->id}")
            ->assertInertia(fn (Assert $p) => $p->has('accessories', 2)->where('accessories.0.name', 'Headset'));
        $this->actingAs($viewer)->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->has('accessories', 1));
    }

    public function test_deleting_a_user_or_an_asset_checks_their_accessories_in(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 3]);
        $user = User::factory()->withoutLogin()->create();
        $asset = Asset::factory()->unassigned()->create();
        $this->service()->checkOut($accessory, $user, 2);
        $this->service()->checkOut($accessory, $asset);
        $admin = User::factory()->withPermissions(['admin' => 1])->create();

        $this->actingAs($admin)->delete("/app/users/{$user->id}")->assertRedirect('/app/users');
        $this->actingAs($admin)->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');

        $this->assertSame(3, $accessory->refresh()->remaining());
        $this->assertSame(3, Activity::query()->where('subject_id', $accessory->id)->where('event', 'checked_in')->count());
    }

    public function test_permissions(): void
    {
        $accessory = Accessory::factory()->create(['qty' => 1]);
        $viewer = User::factory()->withPermissions(['accessories.view' => 1])->create();

        $this->actingAs($viewer)->get("/app/accessories/{$accessory->id}")->assertOk();
        $this->actingAs($viewer)->post("/app/accessories/{$accessory->id}/checkout", [])->assertForbidden();
        $this->service()->checkOut($accessory, User::factory()->create());
        $this->actingAs($viewer)->post('/app/accessory-checkouts/'.$accessory->checkouts()->first()->id.'/checkin')->assertForbidden();
    }

    public function test_the_overview_lists_accessories_running_low(): void
    {
        Mail::fake();
        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->save();
        $low = Accessory::factory()->create(['qty' => 3, 'min_amt' => 1, 'name' => 'Mice']);
        Accessory::factory()->create(['qty' => 10, 'min_amt' => 1]);
        Accessory::factory()->create(['qty' => 0, 'min_amt' => 5, 'archived' => true]);
        $this->service()->checkOut($low, User::factory()->create(), 2);

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->licenseSections['accessory_low']->pluck('id')->all() === [$low->id]
            && str_contains($m->render(), 'Mice'));
    }
}
