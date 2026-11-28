<?php

namespace Tests\Feature\SelfService;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\License;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyItemsTest extends TestCase
{
    use RefreshDatabase;

    private function seat(User $user): License
    {
        $license = License::factory()->seats(1)->create();
        $license->licenseSeats()->first()->forceFill(['assigned_user_id' => $user->id, 'checked_out_at' => now()])->save();

        return $license;
    }

    private function accessory(User $user, Accessory $accessory): void
    {
        AccessoryCheckout::forceCreate([
            'accessory_id' => $accessory->id,
            'assigned_type' => User::class, 'assigned_id' => $user->id, 'checked_out_at' => now(),
        ]);
    }

    private function acceptance(User $user, Asset|Accessory $item, array $attrs = []): CheckoutAcceptance
    {
        $type = $item instanceof Asset ? 'asset' : 'accessory';
        $a = CheckoutAcceptance::create([
            'user_id' => $user->id, 'item_type' => $type,
            'item_id' => $item->id, 'item_name' => 'Thing', 'sent_at' => now(),
        ]);
        $a->forceFill($attrs)->save();

        return $a;
    }

    public function test_a_user_without_permissions_sees_only_their_own_items(): void
    {
        $user = User::factory()->withPermissions([])->create();
        $mine = Asset::factory()->assignedTo($user)->create();
        Asset::factory()->assignedTo(User::factory()->create())->create();
        $license = $this->seat($user);
        $this->accessory($user, Accessory::factory()->create());
        $consumable = Consumable::factory()->create();
        ConsumableAssignment::forceCreate(['consumable_id' => $consumable->id, 'user_id' => $user->id, 'qty' => 2, 'checked_out_at' => now()]);

        $response = $this->actingAs($user)->get('/app/my-items');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('my-items/index')
            ->has('assets', 1, fn (Assert $a) => $a->where('asset_tag', $mine->asset_tag)->where('href', null)->etc())
            ->has('licenses', 1)
            ->has('accessories', 1)
            ->has('consumables', 1)
        );
        $this->assertStringNotContainsString($license->product_key, $response->getContent());
    }

    public function test_items_held_indirectly_are_not_listed(): void
    {
        $user = User::factory()->withPermissions([])->create();
        $parent = Asset::factory()->assignedTo($user)->create();
        Asset::factory()->assignedTo(Location::factory()->create())->create();
        Asset::factory()->assignedTo($parent)->create();
        $license = License::factory()->seats(1)->create();
        $license->licenseSeats()->first()->forceFill(['assigned_asset_id' => $parent->id])->save();

        $this->actingAs($user)->get('/app/my-items')->assertInertia(fn (Assert $page) => $page
            ->has('assets', 1, fn (Assert $a) => $a->where('id', $parent->id)->etc())
            ->has('licenses', 0)
        );
    }

    public function test_links_appear_with_the_view_permission(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => 1])->create();
        $asset = Asset::factory()->assignedTo($user)->create();

        $this->actingAs($user)->get('/app/my-items')->assertInertia(fn (Assert $page) => $page
            ->has('assets', 1, fn (Assert $a) => $a->where('href', "/app/assets/{$asset->id}")->etc())
        );
    }

    public function test_asset_shows_latest_acceptance_status(): void
    {
        $user = User::factory()->withPermissions([])->create();
        $asset = Asset::factory()->assignedTo($user)->create();
        $plain = Asset::factory()->assignedTo($user)->create();
        $old = $this->acceptance($user, $asset, ['declined_at' => now()]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->acceptance($user, $asset);

        $this->actingAs($user)->get('/app/my-items')->assertInertia(function (Assert $page) use ($asset, $plain) {
            $rows = collect($page->toArray()['props']['assets'])->keyBy('id');
            $this->assertSame('pending', $rows[$asset->id]['acceptance']);
            $this->assertNull($rows[$plain->id]['acceptance']);
        });
    }

    public function test_acceptance_status_reflects_only_the_current_checkout(): void
    {
        $user = User::factory()->withPermissions([])->create();
        $asset = Asset::factory()->assignedTo($user)->create(['accepted' => null]);
        // Accepted on an earlier checkout; the item was checked in (accepted reset) and handed out again without a new request.
        $old = $this->acceptance($user, $asset, ['accepted_at' => now()->subDays(10)]);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs($user)->get('/app/my-items')->assertInertia(function (Assert $page) use ($asset) {
            $rows = collect($page->toArray()['props']['assets'])->keyBy('id');
            $this->assertNull($rows[$asset->id]['acceptance']);
        });

        $asset->forceFill(['accepted' => 'accepted'])->save();
        $this->actingAs($user)->get('/app/my-items')->assertInertia(function (Assert $page) use ($asset) {
            $rows = collect($page->toArray()['props']['assets'])->keyBy('id');
            $this->assertSame('accepted', $rows[$asset->id]['acceptance']);
        });
    }

    public function test_pending_acceptances_are_listed(): void
    {
        $user = User::factory()->withPermissions([])->create();
        $acc = Accessory::factory()->create();
        $pending = $this->acceptance($user, $acc);
        $pending->forceFill(['item_name' => 'Headset'])->save();
        $this->acceptance($user, $acc, ['accepted_at' => now()]);

        $this->actingAs($user)->get('/app/my-items')->assertInertia(fn (Assert $page) => $page
            ->has('pendingAcceptances', 1, fn (Assert $a) => $a->where('id', $pending->id)->where('item_name', 'Headset')->etc())
        );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/app/my-items')->assertRedirect('/app/login');
    }

    public function test_the_dashboard_sends_users_without_permissions_to_my_items(): void
    {
        $user = User::factory()->withPermissions([])->create();

        $this->actingAs($user)->get('/app')->assertRedirect('/app/my-items');
    }

    public function test_the_dashboard_stays_for_users_with_any_permission(): void
    {
        $user = User::factory()->withPermissions(['reports.view' => 1])->create();

        $this->actingAs($user)->get('/app')->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }
}
