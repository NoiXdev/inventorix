<?php

namespace Tests\Feature\Checkout;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BulkCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superuser()->create(['login_enabled' => true]);
    }

    private function available(array $attributes = []): Asset
    {
        return Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, ...$attributes]);
    }

    public function test_checks_out_all_selected_assets_to_one_user(): void
    {
        $a = $this->available();
        $b = $this->available();
        $user = User::factory()->withoutLogin()->create();

        $this->actingAs($this->actor())->from('/app/assets')->post('/app/assets/bulk-checkout', [
            'asset_ids' => [$a->id, $b->id],
            'assignee_type' => 'user', 'assignee_id' => $user->id,
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'checkout_at' => '', 'expected_checkin' => '', 'note' => 'Team kit',
        ])->assertRedirect('/app/assets')->assertSessionHas('success', '2 assets checked out.');

        foreach ([$a, $b] as $asset) {
            $asset->refresh();
            $this->assertTrue($asset->assignee->is($user));
            $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
            $this->assertSame(1, $asset->checkout_counter);
            $this->assertSame('Team kit', Activity::query()->where('subject_id', $asset->id)->where('event', 'checked_out')->sole()->getProperty('note'));
        }
    }

    public function test_checkout_to_a_location_moves_every_asset_there(): void
    {
        $a = $this->available();
        $b = $this->available();
        $location = Location::factory()->create();

        $this->actingAs($this->actor())->post('/app/assets/bulk-checkout', [
            'asset_ids' => [$a->id, $b->id], 'assignee_type' => 'location', 'assignee_id' => $location->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($location->id, $a->fresh()->location_id);
        $this->assertSame($location->id, $b->fresh()->location_id);
    }

    public function test_is_all_or_nothing_and_names_the_assets_that_failed(): void
    {
        $ok = $this->available();
        $taken = Asset::factory()->create(['name' => 'Taken laptop', 'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);
        $broken = $this->available(['name' => 'Broken phone', 'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);
        $user = User::factory()->withoutLogin()->create();
        $takenAssignee = $taken->assignee_id;

        $response = $this->actingAs($this->actor())->post('/app/assets/bulk-checkout', [
            'asset_ids' => [$ok->id, $taken->id, $broken->id], 'assignee_type' => 'user', 'assignee_id' => $user->id,
        ])->assertSessionHasErrors('asset_ids');

        $message = session('errors')->first('asset_ids');
        $this->assertStringContainsString('Taken laptop', $message);
        $this->assertStringContainsString('Broken phone', $message);
        $this->assertNull($ok->fresh()->assignee_id);
        $this->assertSame(0, $ok->fresh()->checkout_counter);
        $this->assertSame($takenAssignee, $taken->fresh()->assignee_id);
    }

    public function test_an_asset_cannot_be_checked_out_to_itself_in_a_bulk_checkout(): void
    {
        $a = $this->available();
        $b = $this->available();

        $this->actingAs($this->actor())->post('/app/assets/bulk-checkout', [
            'asset_ids' => [$a->id, $b->id], 'assignee_type' => 'asset', 'assignee_id' => $b->id,
        ])->assertSessionHasErrors('asset_ids');

        $this->assertNull($a->fresh()->assignee_id);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->actor())->post('/app/assets/bulk-checkout', [
            'asset_ids' => [], 'assignee_type' => 'user', 'assignee_id' => 'nope',
            'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id,
        ])->assertSessionHasErrors(['asset_ids', 'assignee_id', 'status_id']);
    }

    public function test_the_page_offers_available_assets_and_takes_over_the_list_selection(): void
    {
        $free = $this->available(['name' => 'Free']);
        $taken = Asset::factory()->create(['name' => 'Taken', 'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);
        $this->available(['name' => 'Broken', 'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);

        $this->actingAs($this->actor())->get("/app/assets/bulk-checkout?ids={$free->id},{$taken->id},garbage")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('assets/bulk-checkout')
                ->has('availableAssets', 1)
                ->where('availableAssets.0.id', $free->id)
                ->where('preselectedIds', [$free->id])
                ->where('skipped', [['name' => 'Taken', 'reason' => 'The asset is already checked out. Check it in first.']])
                ->has('checkoutOptions.assigneeOptions.user')
                ->where('checkoutOptions.rtdLocationName', null)
                ->where('maxAssets', 500));
    }
}
