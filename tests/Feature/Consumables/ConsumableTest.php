<?php

namespace Tests\Feature\Consumables;

use App\Enums\CategoryType;
use App\Exceptions\ConsumableCheckoutException;
use App\Mail\AlertsDigest;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\User;
use App\Services\ConsumableService;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ConsumableTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConsumableService
    {
        return app(ConsumableService::class);
    }

    private function actor(): User
    {
        return User::factory()->withPermissions([
            'consumables.view' => 1, 'consumables.create' => 1, 'consumables.edit' => 1, 'consumables.delete' => 1, 'consumables.checkout' => 1,
        ])->create();
    }

    public function test_create_edit_and_list(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::CONSUMABLE]);

        $this->actingAs($this->actor())->post('/app/consumables', [
            'name' => 'Toner 59A', 'category_id' => $category->id, 'qty' => 10, 'min_amt' => 2, 'item_no' => 'CF259A',
            'purchase_date' => '2026-10-01', 'purchase_cost' => '89.90', 'order_number' => 'PO-7', 'requestable' => '0', 'archived' => '0',
        ])->assertRedirect('/app/consumables')->assertSessionHasNoErrors();

        $consumable = Consumable::query()->sole();
        $this->assertSame('CF259A', $consumable->item_no);
        $this->assertSame('2026-10-01', $consumable->purchase_date->toDateString());

        $this->actingAs($this->actor())->get('/app/consumables')
            ->assertInertia(fn (Assert $p) => $p->component('consumables/index')->where('consumables.data.0.remaining', 10)->where('consumables.data.0.item_no', 'CF259A'));
        $this->actingAs($this->actor())->get("/app/consumables/{$consumable->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('consumable.purchase_date', '2026-10-01'));
    }

    public function test_only_consumable_categories(): void
    {
        $assetCategory = Category::factory()->create(['category_type' => CategoryType::ASSET]);

        $this->actingAs($this->actor())->post('/app/consumables', ['name' => 'X', 'qty' => 1, 'category_id' => $assetCategory->id])
            ->assertSessionHasErrors('category_id');
    }

    public function test_hand_out_to_users_until_none_is_left(): void
    {
        $consumable = Consumable::factory()->create(['qty' => 5]);
        $user = User::factory()->create(['name' => 'Ada']);

        $this->service()->checkOut($consumable, $user, 3, 'for the printer room');
        $this->assertSame(2, $consumable->refresh()->remaining());
        $entry = Activity::query()->where('subject_id', $consumable->id)->where('event', 'checked_out')->sole();
        $this->assertSame('User: Ada (×3)', $entry->getProperty('target'));

        try {
            $this->service()->checkOut($consumable, $user, 3);
            $this->fail('Over-allocation was allowed.');
        } catch (ConsumableCheckoutException $e) {
            $this->assertSame('Only 2 of this consumable are left.', $e->getMessage());
        }

        $consumable->update(['archived' => true]);
        $this->expectExceptionMessage('An archived consumable cannot be checked out.');
        $this->service()->checkOut($consumable, $user);
    }

    public function test_check_out_through_the_route_and_the_user_page(): void
    {
        $consumable = Consumable::factory()->create(['qty' => 4, 'name' => 'Batteries']);
        $user = User::factory()->create();

        $this->actingAs($this->actor())->post("/app/consumables/{$consumable->id}/checkout", ['user_id' => $user->id, 'qty' => 2, 'note' => ''])
            ->assertSessionHas('success', '2 checked out.');
        $this->actingAs($this->actor())->post("/app/consumables/{$consumable->id}/checkout", ['user_id' => $user->id, 'qty' => 9])
            ->assertSessionHasErrors('qty');

        $viewer = User::factory()->withPermissions(['users.view' => 1, 'consumables.view' => 1])->create();
        $this->actingAs($viewer)->get("/app/users/{$user->id}")
            ->assertInertia(fn (Assert $p) => $p->where('consumables.0.name', 'Batteries')->where('consumables.0.qty', 2));
        $this->actingAs($viewer)->get("/app/consumables/{$consumable->id}")
            ->assertInertia(fn (Assert $p) => $p->component('consumables/show')->where('consumable.handed_out', 2)->where('consumable.remaining', 2)
                ->has('assignments', 1)
                ->reloadOnly('checkoutOptions', fn (Assert $r) => $r->where('checkoutOptions', null)));
    }

    public function test_quantity_cannot_drop_below_what_was_handed_out(): void
    {
        $consumable = Consumable::factory()->create(['qty' => 5, 'name' => 'Cables']);
        $this->service()->checkOut($consumable, User::factory()->create(), 3);

        $this->actingAs($this->actor())->put("/app/consumables/{$consumable->id}", ['name' => 'Cables', 'qty' => 2])
            ->assertSessionHasErrors(['qty' => 'The quantity cannot be lower than the 3 units already handed out.']);
        $this->actingAs($this->actor())->put("/app/consumables/{$consumable->id}", ['name' => 'Cables', 'qty' => 3])
            ->assertSessionHasNoErrors();
    }

    public function test_handed_out_records_survive_deleting_the_user(): void
    {
        $consumable = Consumable::factory()->create(['qty' => 5]);
        $user = User::factory()->withoutLogin()->create(['name' => 'Gone User']);
        $this->service()->checkOut($consumable, $user, 2);

        $this->actingAs(User::factory()->withPermissions(['admin' => 1])->create())->delete("/app/users/{$user->id}")->assertRedirect('/app/users');

        $this->assertSame(3, $consumable->refresh()->remaining());
        $this->actingAs($this->actor())->get("/app/consumables/{$consumable->id}")
            ->assertInertia(fn (Assert $p) => $p->where('assignments.0.user_name', 'Gone User')->where('assignments.0.user_url', null));
    }

    public function test_permissions(): void
    {
        $consumable = Consumable::factory()->create(['qty' => 1]);
        $viewer = User::factory()->withPermissions(['consumables.view' => 1])->create();

        $this->actingAs($viewer)->get('/app/consumables')->assertOk();
        $this->actingAs($viewer)->get('/app/consumables/create')->assertForbidden();
        $this->actingAs($viewer)->post("/app/consumables/{$consumable->id}/checkout", [])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/app/consumables')->assertForbidden();
    }

    public function test_the_overview_lists_consumables_running_low(): void
    {
        Mail::fake();
        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->save();
        $low = Consumable::factory()->create(['qty' => 3, 'min_amt' => 1, 'name' => 'Toner']);
        Consumable::factory()->create(['qty' => 10, 'min_amt' => 1]);
        $this->service()->checkOut($low, User::factory()->create(), 2);

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->licenseSections['consumable_low']->pluck('id')->all() === [$low->id]
            && str_contains($m->render(), 'Toner'));
    }
}
