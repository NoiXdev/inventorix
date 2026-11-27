<?php

namespace Tests\Feature\App;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssetBulkEditTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->withPermissions(['assets.view' => 1, 'assets.edit' => 1])->create();
    }

    public function test_only_filled_fields_change_and_cleared_ones_are_emptied(): void
    {
        $a = Asset::factory()->create(['order_number' => 'PO-1', 'notes' => 'keep me', 'requestable' => false]);
        $b = Asset::factory()->create(['order_number' => 'PO-2', 'notes' => 'keep me too', 'requestable' => false]);
        $room = Location::factory()->create();
        $supplier = Supplier::factory()->create();
        $serials = [$a->serial, $b->serial];

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$a->id, $b->id],
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'location_id' => $room->id, 'supplier_id' => $supplier->id,
            'warranty_months' => '36', 'next_audit_date' => '2027-03-01',
            'model_id' => '', 'purchase_cost' => '', 'notes' => '',
            'requestable' => '1', 'archived' => '', 'depreciate' => '',
            'clear' => ['order_number'],
        ])->assertRedirect('/app/assets')->assertSessionHas('success', '2 assets updated.');

        foreach ([$a, $b] as $i => $asset) {
            $asset->refresh();
            $this->assertSame(SystemStatus::STORAGE, $asset->status->system_key);
            $this->assertSame($room->id, $asset->location_id);
            $this->assertSame($supplier->id, $asset->supplier_id);
            $this->assertSame(36, $asset->warranty_months);
            $this->assertSame('2027-03-01', $asset->next_audit_date->toDateString());
            $this->assertNull($asset->order_number);
            $this->assertTrue($asset->requestable);
            $this->assertSame($serials[$i], $asset->serial);
        }
        $this->assertSame('keep me', $a->notes);
        $this->assertSame(2, Activity::query()->whereIn('subject_id', [$a->id, $b->id])->where('event', 'updated')->count());
    }

    public function test_the_eol_follows_a_new_model(): void
    {
        $asset = Asset::factory()->create(['purchase_date' => '2025-01-15', 'eol_explicit' => false]);
        $model = AssetModel::factory()->create(['eol' => 24]);

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', ['asset_ids' => [$asset->id], 'model_id' => $model->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('2027-01-15', $asset->refresh()->asset_eol_date->toDateString());
    }

    public function test_validation(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', ['asset_ids' => [$asset->id]])
            ->assertSessionHasErrors('changes');
        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$asset->id], 'order_number' => 'PO-9', 'clear' => ['order_number'],
        ])->assertSessionHasErrors('order_number');
        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$asset->id], 'clear' => ['status_id', 'serial'],
        ])->assertSessionHasErrors(['clear.0', 'clear.1']);
        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [], 'location_id' => 'nope', 'warranty_months' => '-1', 'requestable' => 'maybe',
        ])->assertSessionHasErrors(['asset_ids', 'location_id', 'warranty_months', 'requestable']);
    }

    public function test_page_and_permission(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->editor())->get("/app/assets/bulk-edit?ids={$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->component('assets/bulk-edit')
                ->where('preselectedIds', [$asset->id])
                ->has('statusOptions')->has('modelOptions')->has('locationOptions')->has('supplierOptions'));

        $viewer = User::factory()->withPermissions(['assets.view' => 1])->create();
        $this->actingAs($viewer)->get('/app/assets/bulk-edit')->assertForbidden();
        $this->actingAs($viewer)->put('/app/assets/bulk-edit', ['asset_ids' => [$asset->id], 'notes' => 'x'])->assertForbidden();
    }
}
