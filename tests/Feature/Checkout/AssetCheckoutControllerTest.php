<?php

namespace Tests\Feature\Checkout;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function available(): Asset
    {
        return Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);
    }

    public function test_checkout_to_a_user(): void
    {
        $asset = $this->available();
        $user = User::factory()->withoutLogin()->create();

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/checkout", [
            'assignee_type' => 'user', 'assignee_id' => $user->id,
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'checkout_at' => '', 'expected_checkin' => now()->addWeek()->toDateString(), 'note' => 'Hi',
        ])->assertRedirect("/app/assets/{$asset->id}")->assertSessionHas('success');

        $asset->refresh();
        $this->assertTrue($asset->assignee->is($user));
        $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
        $this->assertSame(1, $asset->checkout_counter);
    }

    public function test_checkout_validation(): void
    {
        $asset = $this->available();
        $user = User::factory()->withoutLogin()->create();

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/checkout", [
            'assignee_type' => 'location', 'assignee_id' => $user->id,
            'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id,
            'checkout_at' => now()->addDay()->toDateString(),
            'expected_checkin' => now()->subWeek()->toDateString(),
        ])->assertSessionHasErrors(['assignee_id', 'status_id', 'checkout_at', 'expected_checkin']);

        $this->assertNull($asset->fresh()->assignee_id);
    }

    public function test_an_unavailable_asset_or_looping_target_is_refused_with_a_message(): void
    {
        $assigned = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);

        $this->actingAs($this->actor())->from("/app/assets/{$assigned->id}")->post("/app/assets/{$assigned->id}/checkout", [
            'assignee_type' => 'location', 'assignee_id' => Location::factory()->create()->id,
        ])->assertRedirect("/app/assets/{$assigned->id}")->assertSessionHas('error');

        $asset = $this->available();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/checkout", [
            'assignee_type' => 'asset', 'assignee_id' => $asset->id,
        ])->assertSessionHas('error');
        $this->assertNull($asset->fresh()->assignee_id);
    }

    public function test_checkin(): void
    {
        $default = Location::factory()->create();
        $asset = Asset::factory()->create(['rtd_location_id' => $default->id, 'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/checkin", [
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'location_id' => '', 'checkin_at' => '', 'note' => '',
        ])->assertRedirect("/app/assets/{$asset->id}")->assertSessionHas('success');

        $asset->refresh();
        $this->assertNull($asset->assignee_id);
        $this->assertSame($default->id, $asset->location_id);
        $this->assertSame(1, $asset->checkin_counter);

        // Checking in again: it is not checked out any more.
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/checkin", [])->assertSessionHas('error');
        $this->assertSame(1, $asset->fresh()->checkin_counter);
    }

    public function test_show_offers_the_blocker_and_loads_options_on_demand(): void
    {
        $asset = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);

        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('checkoutBlocker', 'Only assets with a deployable status can be checked out.')
                ->missing('checkoutOptions')
                ->reloadOnly('checkoutOptions', fn (Assert $reload) => $reload
                    ->has('checkoutOptions.assigneeOptions.user')
                    ->where('checkoutOptions.checkinStatusId', StatusLabel::system(SystemStatus::STORAGE)->id)
                    ->where('checkoutOptions.deployableStatusOptions', fn ($options) => collect($options)->every(fn ($o) => $o['type'] === 'deployable'))));
    }
}
