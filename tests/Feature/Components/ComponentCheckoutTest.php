<?php

namespace Tests\Feature\Components;

use App\Exceptions\ComponentCheckoutException;
use App\Mail\AlertsDigest;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentAsset;
use App\Models\User;
use App\Services\ComponentService;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ComponentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ComponentService
    {
        return app(ComponentService::class);
    }

    private function actor(): User
    {
        return User::factory()->withPermissions([
            'components.view' => 1, 'components.checkout' => 1, 'components.checkin' => 1, 'assets.view' => 1, 'assets.delete' => 1,
        ])->create();
    }

    public function test_check_out_part_of_the_stock_to_an_asset(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create(['name' => 'Server 1']);

        $row = $this->service()->checkOut($component, $asset, 3, 'upgrade');

        $this->assertSame(3, $row->assigned_qty);
        $this->assertSame(2, $component->refresh()->remaining());
        $entry = Activity::query()->where('subject_id', $component->id)->where('event', 'checked_out')->first();
        $this->assertSame(['type' => 'asset', 'id' => $asset->id, 'name' => 'Server 1'], $entry->getProperty('target'));
        $this->assertSame(3, $entry->getProperty('qty'));
        $this->assertSame('upgrade', $entry->getProperty('note'));
    }

    public function test_more_than_is_left_is_refused_and_nothing_changes(): void
    {
        $component = Component::factory()->create(['qty' => 2]);

        $this->expectException(ComponentCheckoutException::class);
        try {
            $this->service()->checkOut($component, Asset::factory()->create(), 3);
        } finally {
            $this->assertSame(0, ComponentAsset::query()->count());
            $this->assertSame(0, Activity::query()->where('event', 'checked_out')->count());
        }
    }

    public function test_a_trashed_asset_or_component_is_refused(): void
    {
        $component = Component::factory()->create(['qty' => 2]);
        $asset = Asset::factory()->create();
        $asset->delete();
        try {
            $this->service()->checkOut($component, $asset, 1);
            $this->fail('Trashed asset accepted.');
        } catch (ComponentCheckoutException) {
            $this->assertSame(0, ComponentAsset::query()->count());
        }

        $trashed = Component::factory()->create(['qty' => 2]);
        $trashed->delete();
        $this->expectException(ComponentCheckoutException::class);
        $this->service()->checkOut($trashed, Asset::factory()->create(), 1);
    }

    public function test_partial_and_full_check_in(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $row = $this->service()->checkOut($component, Asset::factory()->create(), 3);

        $this->service()->checkIn($row, 2, 'swapped');
        $this->assertSame(1, $row->refresh()->assigned_qty);
        $this->assertSame(4, $component->refresh()->remaining());

        $this->service()->checkIn($row, 1);
        $this->assertNull(ComponentAsset::query()->find($row->id));
        $this->assertSame(5, $component->refresh()->remaining());
        $this->assertSame(2, Activity::query()->where('subject_id', $component->id)->where('event', 'checked_in')->count());
    }

    public function test_checking_in_more_than_the_row_holds_is_refused(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $row = $this->service()->checkOut($component, Asset::factory()->create(), 1);

        $this->expectException(ComponentCheckoutException::class);
        try {
            $this->service()->checkIn($row, 3);
        } finally {
            $this->assertSame(1, $row->refresh()->assigned_qty);
        }
    }

    public function test_the_endpoints_check_out_and_in(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create();
        $actor = $this->actor();

        $this->actingAs($actor)->post("/app/components/{$component->id}/checkout", ['asset_id' => $asset->id, 'qty' => 4])->assertSessionHasNoErrors();
        $row = ComponentAsset::query()->firstOrFail();
        $this->assertSame(4, $row->assigned_qty);

        $this->actingAs($actor)->post("/app/components/{$component->id}/checkout", ['asset_id' => $asset->id, 'qty' => 2])->assertSessionHasErrors('qty');
        $this->actingAs($actor)->post("/app/components/{$component->id}/checkout", ['asset_id' => $asset->id, 'qty' => 0])->assertSessionHasErrors('qty');

        $this->actingAs($actor)->post("/app/component-assets/{$row->id}/checkin", ['qty' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $row->refresh()->assigned_qty);
        $this->actingAs($actor)->post("/app/component-assets/{$row->id}/checkin", ['qty' => 5])->assertSessionHasErrors('qty');
    }

    public function test_trashed_asset_is_a_validation_error(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create();
        $asset->delete();

        $this->actingAs($this->actor())->post("/app/components/{$component->id}/checkout", ['asset_id' => $asset->id, 'qty' => 1])
            ->assertSessionHasErrors('asset_id');
    }

    public function test_deleting_an_asset_checks_its_components_in(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create();
        $this->service()->checkOut($component, $asset, 2);

        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');

        $this->assertSame(0, ComponentAsset::query()->count());
        $this->assertSame(5, $component->refresh()->remaining());
        $this->assertSame('Asset deleted', Activity::query()->where('subject_id', $component->id)->where('event', 'checked_in')->first()->getProperty('note'));
    }

    public function test_the_asset_page_lists_its_components(): void
    {
        $component = Component::factory()->create(['qty' => 5, 'name' => 'RAM 16GB', 'serial' => 'S1']);
        $asset = Asset::factory()->create();
        $row = $this->service()->checkOut($component, $asset, 2, 'slot 1');

        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")->assertInertia(fn (Assert $page) => $page
            ->has('components', 1)
            ->where('components.0.id', $row->id)
            ->where('components.0.component_id', $component->id)
            ->where('components.0.name', 'RAM 16GB')
            ->where('components.0.serial', 'S1')
            ->where('components.0.qty', 2)
            ->where('components.0.note', 'slot 1'));

        $noView = User::factory()->withPermissions(['assets.view' => 1])->create();
        $this->actingAs($noView)->get("/app/assets/{$asset->id}")->assertInertia(fn (Assert $page) => $page->where('components', null));
    }

    public function test_the_component_page_lists_checkouts_and_history(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create();
        $row = $this->service()->checkOut($component, $asset, 2);

        $this->actingAs($this->actor())->get("/app/components/{$component->id}")->assertInertia(fn (Assert $page) => $page
            ->has('checkouts', 1)
            ->where('checkouts.0.id', $row->id)
            ->where('checkouts.0.qty', 2)
            ->where('checkouts.0.asset.url', "/app/assets/{$asset->id}")
            ->where('history.0.event', 'checked_out')
            ->where('history.0.event_label', 'Checked out'));
    }

    public function test_permissions_are_required(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        $asset = Asset::factory()->create();
        $viewer = User::factory()->withPermissions(['components.view' => 1])->create();
        $row = $this->service()->checkOut($component, $asset, 1);

        $this->actingAs($viewer)->post("/app/components/{$component->id}/checkout", ['asset_id' => $asset->id, 'qty' => 1])->assertForbidden();
        $this->actingAs($viewer)->post("/app/component-assets/{$row->id}/checkin", ['qty' => 1])->assertForbidden();
    }

    public function test_the_overview_lists_components_running_low(): void
    {
        Mail::fake();
        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->save();
        $low = Component::factory()->create(['qty' => 3, 'min_amt' => 1, 'name' => 'Fans']);
        Component::factory()->create(['qty' => 10, 'min_amt' => 1]);
        $this->service()->checkOut($low, Asset::factory()->create(), 2);

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->licenseSections['component_low']->pluck('id')->all() === [$low->id]
            && str_contains($m->render(), 'Fans'));
    }
}
