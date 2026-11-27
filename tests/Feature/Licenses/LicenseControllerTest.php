<?php

namespace Tests\Feature\Licenses;

use App\Enums\CategoryType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LicenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withPermissions(['admin' => 1])->create();
    }

    public function test_create_with_seats_and_key(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::LICENSE]);

        $this->actingAs($this->admin())->post('/app/licenses', [
            'name' => 'Office', 'seats' => 3, 'product_key' => 'KEY-1', 'category_id' => $category->id,
            'reassignable' => true, 'maintained' => false, 'requestable' => false, 'expiration_date' => '2027-01-31',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $license = License::query()->sole();
        $this->assertSame(3, $license->licenseSeats()->count());
        $this->assertSame('KEY-1', $license->product_key);
        $this->assertSame('2027-01-31', $license->expiration_date->toDateString());
    }

    public function test_only_license_categories_are_accepted(): void
    {
        $assetCategory = Category::factory()->create(['category_type' => CategoryType::ASSET]);

        $this->actingAs($this->admin())->post('/app/licenses', ['name' => 'X', 'seats' => 1, 'category_id' => $assetCategory->id])
            ->assertSessionHasErrors('category_id');
    }

    public function test_product_keys_need_their_own_permission(): void
    {
        $license = License::factory()->create(['product_key' => 'SECRET-KEY']);
        $viewer = User::factory()->withPermissions(['licenses.view' => 1, 'licenses.edit' => 1])->create();

        $this->actingAs($viewer)->get("/app/licenses/{$license->id}")
            ->assertInertia(fn (Assert $p) => $p->where('license.product_key', null)->where('license.has_product_key', true));
        $this->actingAs($viewer)->get("/app/licenses/{$license->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('license.product_key', null));
        $this->actingAs($viewer)->put("/app/licenses/{$license->id}", ['name' => 'Renamed', 'seats' => 1, 'product_key' => 'MINE'])
            ->assertSessionHasErrors('product_key');
        // Without the field the key stays.
        $this->actingAs($viewer)->put("/app/licenses/{$license->id}", ['name' => 'Renamed', 'seats' => 1])->assertSessionHasNoErrors();
        $this->assertSame('SECRET-KEY', $license->refresh()->product_key);

        $this->actingAs(User::factory()->withPermissions(['licenses.view' => 1, 'licenses.keys' => 1])->create())
            ->get("/app/licenses/{$license->id}")
            ->assertInertia(fn (Assert $p) => $p->where('license.product_key', 'SECRET-KEY'));
    }

    public function test_reducing_seats_below_the_checked_out_ones_is_refused(): void
    {
        $license = License::factory()->seats(2)->create();
        app(LicenseService::class)->checkOut($license, User::factory()->create());
        app(LicenseService::class)->checkOut($license, User::factory()->create());

        $this->actingAs($this->admin())->put("/app/licenses/{$license->id}", ['name' => $license->name, 'seats' => 1])
            ->assertSessionHasErrors('seats');
        $this->assertSame(2, $license->refresh()->seats);
    }

    public function test_seat_check_out_and_in_through_the_routes(): void
    {
        $license = License::factory()->seats(1)->create();
        $user = User::factory()->create();
        $actor = User::factory()->withPermissions(['licenses.view' => 1, 'licenses.checkout' => 1, 'licenses.checkin' => 1])->create();

        $this->actingAs($actor)->post("/app/licenses/{$license->id}/checkout", ['assignee_type' => 'user', 'assignee_id' => $user->id])
            ->assertSessionHas('success');
        $seat = $license->licenseSeats()->sole();
        $this->assertSame($user->id, $seat->assigned_user_id);

        $this->actingAs($actor)->post("/app/licenses/{$license->id}/checkout", ['assignee_type' => 'user', 'assignee_id' => $user->id])
            ->assertSessionHasErrors('assignee_id');

        $this->actingAs($actor)->post("/app/license-seats/{$seat->id}/checkin", ['note' => 'done'])->assertSessionHas('success');
        $this->assertNull($seat->refresh()->assigned_user_id);
    }

    public function test_a_license_with_checked_out_seats_cannot_be_deleted(): void
    {
        $license = License::factory()->seats(1)->create();
        app(LicenseService::class)->checkOut($license, User::factory()->create());

        $this->actingAs($this->admin())->delete("/app/licenses/{$license->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($license);
    }

    public function test_permissions(): void
    {
        $license = License::factory()->seats(1)->create();
        $viewer = User::factory()->withPermissions(['licenses.view' => 1])->create();

        $this->actingAs($viewer)->get('/app/licenses')->assertOk();
        $this->actingAs($viewer)->get('/app/licenses/create')->assertForbidden();
        $this->actingAs($viewer)->delete("/app/licenses/{$license->id}")->assertForbidden();
        $this->actingAs($viewer)->post("/app/licenses/{$license->id}/checkout", [])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/app/licenses')->assertForbidden();
    }

    public function test_asset_and_user_pages_list_their_seats(): void
    {
        $license = License::factory()->seats(2)->create(['name' => 'CAD Suite']);
        $user = User::factory()->create();
        $asset = Asset::factory()->create();
        app(LicenseService::class)->checkOut($license, $user);
        app(LicenseService::class)->checkOut($license, $asset);
        $viewer = User::factory()->withPermissions(['assets.view' => 1, 'users.view' => 1, 'licenses.view' => 1])->create();

        $this->actingAs($viewer)->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('licenses.0.name', 'CAD Suite'));
        $this->actingAs($viewer)->get("/app/users/{$user->id}")
            ->assertInertia(fn (Assert $p) => $p->where('licenses.0.name', 'CAD Suite'));
        $this->actingAs(User::factory()->withPermissions(['assets.view' => 1])->create())->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('licenses', null));
    }

    public function test_deleting_a_user_or_an_asset_checks_their_seats_in(): void
    {
        $reassignable = License::factory()->seats(1)->create(['reassignable' => true]);
        $oneWay = License::factory()->seats(1)->create(['reassignable' => false]);
        $user = User::factory()->withoutLogin()->create();
        $asset = Asset::factory()->unassigned()->create();
        app(LicenseService::class)->checkOut($reassignable, $user);
        app(LicenseService::class)->checkOut($oneWay, $asset);

        $this->actingAs($this->admin())->delete("/app/users/{$user->id}")->assertRedirect('/app/users');
        $this->actingAs($this->admin())->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');

        $this->assertSame(1, $reassignable->freeSeats()->count());
        $this->assertTrue($oneWay->licenseSeats()->sole()->retired);
        $this->assertSame('User deleted', Activity::query()
            ->where('subject_id', $reassignable->id)->where('event', 'checked_in')->sole()->getProperty('note'));
    }

    public function test_key_edge_cases(): void
    {
        $license = License::factory()->create(['product_key' => 'KEEP-ME']);
        $noKeys = User::factory()->withPermissions(['licenses.view' => 1, 'licenses.edit' => 1])->create();

        // An empty field from someone without licenses.keys never wipes the key ...
        $this->actingAs($noKeys)->put("/app/licenses/{$license->id}", ['name' => 'X', 'seats' => 1, 'product_key' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('KEEP-ME', $license->refresh()->product_key);
        // ... and a value is refused.
        $this->actingAs($noKeys)->put("/app/licenses/{$license->id}", ['name' => 'X', 'seats' => 1, 'product_key' => 'MINE'])
            ->assertSessionHasErrors(['product_key' => 'The product key field is prohibited.']);
        $this->assertSame('KEEP-ME', $license->refresh()->product_key);

        $keys = User::factory()->withPermissions(['licenses.view' => 1, 'licenses.edit' => 1, 'licenses.keys' => 1])->create();
        $this->actingAs($keys)->put("/app/licenses/{$license->id}", ['name' => 'X', 'seats' => 1, 'product_key' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($license->refresh()->product_key);

        $license->update(['product_key' => 'SECRET']);
        $this->assertArrayNotHasKey('product_key', $license->refresh()->toArray());
        $this->assertStringNotContainsString('SECRET', $license->toJson());
    }

    public function test_an_undecryptable_key_does_not_break_the_page(): void
    {
        $license = License::factory()->create();
        \DB::table('licenses')->where('id', $license->id)->update(['product_key' => 'not-a-ciphertext']);

        $this->actingAs(User::factory()->superuser()->create())->get("/app/licenses/{$license->id}")
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('license.product_key', null)->where('license.has_product_key', true));
    }

    public function test_check_out_options_and_deleted_targets(): void
    {
        $license = License::factory()->seats(1)->create();
        $viewer = User::factory()->withPermissions(['licenses.view' => 1])->create();
        $this->actingAs($viewer)->get("/app/licenses/{$license->id}")
            ->assertInertia(fn (Assert $p) => $p->reloadOnly('checkoutOptions', fn (Assert $r) => $r->where('checkoutOptions', null)));

        $actor = User::factory()->withPermissions(['licenses.view' => 1, 'licenses.checkout' => 1])->create();
        $this->actingAs($actor)->get("/app/licenses/{$license->id}")
            ->assertInertia(fn (Assert $p) => $p->reloadOnly('checkoutOptions', fn (Assert $r) => $r->has('checkoutOptions.user')->has('checkoutOptions.asset')->missing('checkoutOptions.location')));

        $gone = Asset::factory()->create();
        $gone->delete();
        $this->actingAs($actor)->post("/app/licenses/{$license->id}/checkout", ['assignee_type' => 'asset', 'assignee_id' => $gone->id])
            ->assertSessionHasErrors('assignee_id');
        $this->assertSame(1, $license->freeSeats()->count());
    }
}
