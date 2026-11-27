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

    public function test_checked_out_assets_keep_a_deployable_or_pending_status_and_are_not_archived(): void
    {
        $out = Asset::factory()->create(['name' => 'Lent laptop']);
        $free = Asset::factory()->unassigned()->create();
        $before = $out->status_id;

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$out->id, $free->id], 'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id,
        ])->assertSessionHasErrors('status_id');
        $this->assertStringContainsString('Lent laptop', session('errors')->first('status_id'));
        $this->assertSame($before, $out->refresh()->status_id);
        $this->assertNotSame(StatusLabel::system(SystemStatus::DEFECT)->id, $free->refresh()->status_id);

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', ['asset_ids' => [$out->id], 'archived' => '1'])
            ->assertSessionHasErrors('archived');
        $this->assertFalse((bool) $out->refresh()->archived);

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$out->id], 'status_id' => StatusLabel::system(SystemStatus::LEND)->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(StatusLabel::system(SystemStatus::LEND)->id, $out->refresh()->status_id);
    }

    public function test_unassigned_assets_take_any_status_and_yes_no_fields_both_ways(): void
    {
        $asset = Asset::factory()->unassigned()->create(['requestable' => true, 'archived' => false]);

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$asset->id], 'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id,
            'requestable' => '0', 'archived' => '1', 'purchase_cost' => '1234.56',
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame(SystemStatus::DEFECT, $asset->status->system_key);
        $this->assertFalse($asset->requestable);
        $this->assertTrue($asset->archived);
        $this->assertSame('1234.56', (string) $asset->purchase_cost);
    }

    public function test_assets_checked_out_to_an_edited_asset_move_with_it(): void
    {
        $dock = Asset::factory()->unassigned()->create();
        $child = Asset::factory()->assignedTo($dock)->create(['location_id' => $dock->location_id]);
        $room = Location::factory()->create();

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', ['asset_ids' => [$dock->id], 'location_id' => $room->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($room->id, $child->refresh()->location_id);
    }

    public function test_deleted_locations_and_suppliers_are_refused(): void
    {
        $asset = Asset::factory()->create();
        $location = Location::factory()->create();
        $supplier = Supplier::factory()->create();
        $location->delete();
        $supplier->delete();

        $this->actingAs($this->editor())->put('/app/assets/bulk-edit', [
            'asset_ids' => [$asset->id], 'location_id' => $location->id, 'supplier_id' => $supplier->id,
        ])->assertSessionHasErrors(['location_id', 'supplier_id']);
    }
}
