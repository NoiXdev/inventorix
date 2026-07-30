<?php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Incident;
use App\Models\Manufacturer;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_assets_with_relation_columns_and_incident_count(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $type = AssetType::factory()->create(['name' => 'Laptop']);
        $owner = Person::factory()->create(['name' => 'Ada Lovelace']);
        $asset = Asset::factory()->create([
            'model_id' => $model->id, 'asset_type_id' => $type->id, 'owner_id' => $owner->id,
        ]);
        Incident::factory()->count(2)->create(['asset_id' => $asset->id]);

        $this->actingAs($this->actor())->get('/app/assets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/index')
                ->has('assets.data', 1, fn (Assert $r) => $r
                    ->where('asset_type_name', 'Laptop')
                    ->where('manufacturer_name', 'Acme')
                    ->where('model_name', 'X1')
                    ->where('owner_name', 'Ada Lovelace')
                    ->where('incidents_count', 2)
                    ->etc())
                ->has('stateOptions')->has('assetTypeOptions')->has('manufacturerOptions'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/assets')->assertRedirect();
    }

    public function test_index_filters_by_state(): void
    {
        Asset::factory()->create(['state' => AssetState::IN_USE->value]);
        Asset::factory()->create(['state' => AssetState::DEFECT->value]);

        $this->actingAs($this->actor())->get('/app/assets?filter[state]=in-use')
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 1)
                ->where('assets.data.0.state', 'in-use'));
    }

    public function test_index_filters_by_manufacturer(): void
    {
        $a = Manufacturer::factory()->create();
        $b = Manufacturer::factory()->create();
        $ma = AssetModel::factory()->create(['manufacturer_id' => $a->id]);
        $mb = AssetModel::factory()->create(['manufacturer_id' => $b->id]);
        Asset::factory()->count(2)->create(['model_id' => $ma->id]);
        Asset::factory()->create(['model_id' => $mb->id]);

        $this->actingAs($this->actor())->get("/app/assets?filter[manufacturer_id]={$a->id}")
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 2));
    }

    public function test_index_sorts_by_manufacturer_name(): void
    {
        $z = Manufacturer::factory()->create(['name' => 'Zeta']);
        $al = Manufacturer::factory()->create(['name' => 'Alpha']);
        Asset::factory()->create(['model_id' => AssetModel::factory()->create(['manufacturer_id' => $z->id])->id]);
        Asset::factory()->create(['model_id' => AssetModel::factory()->create(['manufacturer_id' => $al->id])->id]);

        $this->actingAs($this->actor())->get('/app/assets?sort=manufacturer_name')
            ->assertInertia(fn (Assert $p) => $p->where('assets.data.0.manufacturer_name', 'Alpha'));
    }

    public function test_index_sorts_by_incidents_count(): void
    {
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        Incident::factory()->count(1)->create(['asset_id' => $assetA->id]);
        Incident::factory()->count(3)->create(['asset_id' => $assetB->id]);

        $this->actingAs($this->actor())->get('/app/assets?sort=-incidents_count')
            ->assertInertia(fn (Assert $p) => $p->where('assets.data.0.incidents_count', 3));
    }

    public function test_store_creates_asset_and_syncs_tags(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', [
            'state' => 'in-use', 'asset_type_id' => $type->id,
            'owner_id' => '', 'place_id' => '', 'model_id' => '',
            'serial_number' => 'SN-1', 'buy_price' => '199.99',
            'buy_date' => '2024-01-10', 'guarantee_end' => '2026-01-10',
            'buy_type' => 'once', 'invoice' => 'INV-1',
            'tags' => ['portable', 'audited'],
        ])->assertRedirect('/app/assets');

        $asset = Asset::where('serial_number', 'SN-1')->first();
        $this->assertNotNull($asset);
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertNull($asset->owner_id); // empty string normalized to null
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_store_validates_required_state_and_type(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', ['state' => '', 'asset_type_id' => ''])
            ->assertSessionHasErrors(['state', 'asset_type_id']);
    }

    public function test_store_rejects_invalid_state_enum(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', ['state' => 'bogus', 'asset_type_id' => $type->id])
            ->assertSessionHasErrors('state');
    }

    public function test_store_rejects_nonexistent_owner(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', [
            'state' => 'new', 'asset_type_id' => $type->id, 'owner_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertSessionHasErrors('owner_id');
    }

    public function test_update_changes_fields_and_tags(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $asset->syncTags(['old']);

        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}", [
            'state' => 'storage', 'asset_type_id' => $asset->asset_type_id,
            'owner_id' => '', 'place_id' => '', 'model_id' => '',
            'serial_number' => 'SN-2', 'buy_price' => '', 'buy_date' => '', 'guarantee_end' => '',
            'buy_type' => '', 'invoice' => '', 'tags' => ['new-tag'],
        ])->assertRedirect('/app/assets');

        $asset->refresh();
        $this->assertSame(AssetState::STORAGE, $asset->state);
        $this->assertEqualsCanonicalizing(['new-tag'], $asset->tags->pluck('name')->all());
    }

    public function test_show_returns_detail_props(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $asset = Asset::factory()->create(['model_id' => $model->id]);
        $asset->syncTags(['t1']);

        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/show')
                ->where('asset.id', $asset->id)
                ->where('asset.manufacturer_name', 'Acme')
                ->where('asset.model_name', 'X1')
                ->where('asset.tags', ['t1'])
                ->etc());
    }

    public function test_show_returns_attachments_and_category_options(): void
    {
        $asset = Asset::factory()->create();
        $asset->attachments()->create([
            'path' => 'attachments/z.pdf', 'original_name' => 'z.pdf', 'mime_type' => 'application/pdf',
            'size' => 2048, 'type' => 'document', 'category' => 'dokument', 'title' => 'Z',
        ]);

        $this->actingAs(User::factory()->create(['login_enabled' => true]))
            ->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/show')
                ->has('attachments', 1, fn ($a) => $a
                    ->where('type', 'document')->where('original_name', 'z.pdf')
                    ->where('size_label', '2 KB')->has('url')->etc())
                ->has('attachmentCategoryOptions')
                ->etc());
    }

    public function test_show_returns_incidents_prop(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->create(['asset_id' => $asset->id, 'title' => 'Boom', 'closed_date' => null]);

        $this->actingAs(User::factory()->create(['login_enabled' => true]))
            ->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/show')
                ->has('incidents', 1, fn ($i) => $i
                    ->where('title', 'Boom')->where('status', 'open')->has('open_date')->etc())
                ->etc());
    }

    public function test_destroy_deletes_asset(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');
        $this->assertNull(Asset::find($asset->id));
    }

    public function test_index_exposes_flashed_import_result(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]))
            ->withSession(['importResult' => ['imported' => 3, 'failed' => []]])
            ->get('/app/assets')
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/index')
                ->where('importResult.imported', 3)
                ->etc());
    }
}
