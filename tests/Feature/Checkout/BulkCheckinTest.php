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

class BulkCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->withPermissions(['assets.view' => 1, 'assets.checkin' => 1])->create();
    }

    public function test_checks_in_all_selected_assets(): void
    {
        $a = Asset::factory()->create(['expected_checkin' => now()->addDay()]);
        $b = Asset::factory()->create();
        $store = Location::factory()->create();

        $this->actingAs($this->actor())->from('/app/assets/bulk-checkin')->post('/app/assets/bulk-checkin', [
            'asset_ids' => [$a->id, $b->id],
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'location_id' => $store->id, 'checkin_at' => '', 'note' => 'Back from the fair',
        ])->assertRedirect('/app/assets')->assertSessionHas('success', '2 assets checked in.');

        foreach ([$a, $b] as $asset) {
            $asset->refresh();
            $this->assertNull($asset->assignee_id);
            $this->assertNull($asset->expected_checkin);
            $this->assertSame($store->id, $asset->location_id);
            $this->assertSame(SystemStatus::STORAGE, $asset->status->system_key);
            $this->assertSame(1, $asset->checkin_counter);
            $this->assertSame('Back from the fair', Activity::query()->where('subject_id', $asset->id)->where('event', 'checked_in')->sole()->getProperty('note'));
        }
    }

    public function test_is_all_or_nothing_and_names_assets_that_are_not_checked_out(): void
    {
        $out = Asset::factory()->create();
        $inStore = Asset::factory()->unassigned()->create(['name' => 'Spare monitor']);

        $this->actingAs($this->actor())->post('/app/assets/bulk-checkin', ['asset_ids' => [$out->id, $inStore->id]])
            ->assertSessionHasErrors('asset_ids');

        $this->assertStringContainsString('Spare monitor', session('errors')->first('asset_ids'));
        $this->assertNotNull($out->refresh()->assignee_id);
    }

    public function test_page_offers_checked_out_assets_and_takes_over_the_selection(): void
    {
        $out = Asset::factory()->create();
        $free = Asset::factory()->unassigned()->create(['name' => 'Free']);

        $this->actingAs($this->actor())->get("/app/assets/bulk-checkin?ids={$out->id},{$free->id}")
            ->assertInertia(fn (Assert $p) => $p->component('assets/bulk-checkin')
                ->has('availableAssets', 1)
                ->where('availableAssets.0.assignee_name', $out->assigneeName())
                ->where('preselectedIds', [$out->id])
                ->where('skipped', [['name' => 'Free', 'reason' => 'The asset is not checked out.']])
                ->where('checkinStatusId', StatusLabel::system(SystemStatus::STORAGE)->id));
    }

    public function test_needs_assets_checkin(): void
    {
        $viewer = User::factory()->withPermissions(['assets.view' => 1, 'assets.checkout' => 1])->create();
        $asset = Asset::factory()->create();

        $this->actingAs($viewer)->get('/app/assets/bulk-checkin')->assertForbidden();
        $this->actingAs($viewer)->post('/app/assets/bulk-checkin', ['asset_ids' => [$asset->id]])->assertForbidden();
    }
}
