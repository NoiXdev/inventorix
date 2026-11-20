<?php

namespace Tests\Feature\App;

use App\Enums\CategoryType;
use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Handover;
use App\Models\Incident;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $type = Category::factory()->create(['name' => 'Laptop']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id, 'category_id' => $type->id]);
        $owner = User::factory()->withoutLogin()->create(['name' => 'Ada Lovelace']);
        $asset = Asset::factory()->create([
            'model_id' => $model->id, 'assignee_type' => User::class, 'assignee_id' => $owner->id,
        ]);
        Incident::factory()->count(2)->create(['asset_id' => $asset->id]);

        $this->actingAs($this->actor())->get('/app/assets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/index')
                ->has('assets.data', 1, fn (Assert $r) => $r
                    ->where('category_name', 'Laptop')
                    ->where('manufacturer_name', 'Acme')
                    ->where('model_name', 'X1')
                    ->where('assignee_name', 'Ada Lovelace')
                    ->where('incidents_count', 2)
                    ->etc())
                ->has('statusOptions')->has('categoryOptions')->has('manufacturerOptions'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/assets')->assertRedirect();
    }

    public function test_index_filters_by_status(): void
    {
        Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);
        Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id]);

        $inUse = StatusLabel::system(SystemStatus::IN_USE);
        $this->actingAs($this->actor())->get('/app/assets?filter[status_id]='.$inUse->id)
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 1)
                ->where('assets.data.0.status', ['id' => $inUse->id, 'name' => 'In Benutzung', 'type' => 'deployable']));
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
        $this->actingAs($this->actor())->post('/app/assets', [
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'assignee_type' => '', 'assignee_id' => '', 'location_id' => '', 'model_id' => '',
            'name' => 'Laptop Ada', 'asset_tag' => 'IT-0001',
            'serial' => 'SN-1', 'purchase_cost' => '199.99',
            'purchase_date' => '2024-01-10', 'warranty_months' => '24',
            'order_number' => 'PO-1',
            'tags' => ['portable', 'audited'],
        ])->assertRedirect('/app/assets');

        $asset = Asset::where('serial', 'SN-1')->first();
        $this->assertNotNull($asset);
        $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
        $this->assertNull($asset->assignee_id); // empty string normalized to null
        $this->assertNull($asset->assignee_type);
        $this->assertSame('2026-01-10', $asset->warrantyEnd()->toDateString());
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_store_validates_required_status(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', ['status_id' => ''])
            ->assertSessionHasErrors(['status_id']);
    }

    public function test_store_rejects_invalid_state_enum(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', ['status_id' => 'bogus'])
            ->assertSessionHasErrors('status_id');
    }

    public function test_store_rejects_nonexistent_owner(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', [
            'status_id' => StatusLabel::system(SystemStatus::NEW)->id, 'assignee_type' => 'user', 'assignee_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertSessionHasErrors('assignee_id');
    }

    /**
     * Regression for the tracked quick-add-owner gap: the inline "quick add owner"
     * flow on the asset form must produce a `users` row, since AssetRequest validates
     * the user assignee against `exists:users,id`. Exercises the real route end to end.
     */
    public function test_an_owner_created_via_the_quick_add_flow_can_be_used_to_save_an_asset(): void
    {

        $quickAdd = $this->actingAs($this->actor())
            ->postJson('/app/users/quick', ['firstname' => 'Ada', 'lastname' => 'Lovelace'])
            ->assertCreated();
        $ownerId = $quickAdd->json('id');

        $this->actingAs($this->actor())->post('/app/assets', [
            'status_id' => StatusLabel::system(SystemStatus::NEW)->id, 'assignee_type' => 'user', 'assignee_id' => $ownerId,
        ])->assertRedirect('/app/assets')->assertSessionHasNoErrors();

        $asset = Asset::where('assignee_id', $ownerId)->first();
        $this->assertNotNull($asset);
        $this->assertSame('Ada Lovelace', $asset->assignee->name);
    }

    public function test_update_changes_fields_and_tags(): void
    {
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id]);
        $asset->syncTags(['old']);

        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}", [
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'assignee_type' => '', 'assignee_id' => '', 'location_id' => '', 'model_id' => '',
            'serial' => 'SN-2', 'purchase_cost' => '', 'purchase_date' => '', 'warranty_months' => '',
            'order_number' => '', 'tags' => ['new-tag'],
        ])->assertRedirect('/app/assets');

        $asset->refresh();
        $this->assertSame(SystemStatus::STORAGE, $asset->status->system_key);
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

    public function test_category_comes_from_the_model(): void
    {
        $category = Category::factory()->create(['name' => 'Notebook']);
        $other = Category::factory()->create(['name' => 'Monitor']);
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $asset = Asset::factory()->create(['model_id' => $model->id]);
        Asset::factory()->create(['model_id' => AssetModel::factory()->create(['category_id' => $other->id])->id]);
        Asset::factory()->create(['model_id' => null]);

        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('asset.category_name', 'Notebook')->etc());

        $this->actingAs($this->actor())->get("/app/assets?filter[category_id]={$category->id}")
            ->assertInertia(fn (Assert $p) => $p
                ->has('assets.data', 1)
                ->where('assets.data.0.id', $asset->id)
                ->where('assets.data.0.category_name', 'Notebook')
                ->etc());
    }

    public function test_category_filter_only_offers_asset_categories(): void
    {
        $assets = Category::factory()->create(['name' => 'Laptop']);
        Category::factory()->create(['name' => 'Cable', 'category_type' => CategoryType::ACCESSORY]);

        $this->actingAs($this->actor())->get('/app/assets')
            ->assertInertia(fn (Assert $p) => $p
                ->where('categoryOptions', [['value' => $assets->id, 'label' => 'Laptop']])
                ->etc());
    }

    public function test_old_asset_types_url_redirects_to_asset_categories(): void
    {
        $this->actingAs($this->actor())->get('/app/asset-types')
            ->assertRedirect('/app/categories?filter%5Bcategory_type%5D=asset');
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'status_id' => StatusLabel::system(SystemStatus::NEW)->id,
            'assignee_type' => '', 'assignee_id' => '', 'location_id' => '', 'model_id' => '',
            ...$overrides,
        ];
    }

    public function test_assets_can_be_assigned_to_a_location_or_another_asset(): void
    {
        $location = Location::factory()->create(['name' => 'Lager']);
        $dock = Asset::factory()->create(['name' => 'Dock']);

        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['name' => 'A', 'assignee_type' => 'location', 'assignee_id' => $location->id]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['name' => 'B', 'assignee_type' => 'asset', 'assignee_id' => $dock->id]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Asset::firstWhere('name', 'A')->assignee->is($location));
        $this->assertTrue(Asset::firstWhere('name', 'B')->assignee->is($dock));

        $this->actingAs($this->actor())->get('/app/assets/'.Asset::firstWhere('name', 'B')->id)
            ->assertInertia(fn (Assert $p) => $p
                ->where('asset.assignee', ['type' => 'asset', 'name' => 'Dock', 'url' => "/app/assets/{$dock->id}"])
                ->etc());
    }

    public function test_assignee_must_exist_in_its_type_and_needs_a_deployable_status(): void
    {
        $user = User::factory()->withoutLogin()->create();

        // A user id is not a location.
        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['assignee_type' => 'location', 'assignee_id' => $user->id]))
            ->assertSessionHasErrors('assignee_id');
        // Type without id.
        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['assignee_type' => 'user']))
            ->assertSessionHasErrors('assignee_id');
        // Checking out on creation needs a deployable status.
        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload([
                'status_id' => StatusLabel::system(SystemStatus::DEFECT)->id,
                'assignee_type' => 'user', 'assignee_id' => $user->id,
            ]))
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_creating_with_an_assignee_is_a_checkout(): void
    {
        $user = User::factory()->withoutLogin()->create();

        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['name' => 'Laptop', 'assignee_type' => 'user', 'assignee_id' => $user->id]))
            ->assertSessionHasNoErrors();

        $asset = Asset::firstWhere('name', 'Laptop');
        $this->assertTrue($asset->assignee->is($user));
        $this->assertSame(1, $asset->checkout_counter);
        $this->assertNotNull($asset->last_checkout);
    }

    public function test_update_does_not_change_the_assignee(): void
    {
        $owner = User::factory()->withoutLogin()->create();
        $other = User::factory()->withoutLogin()->create();
        $asset = Asset::factory()->assignedTo($owner)->create();

        $this->actingAs($this->actor())
            ->put("/app/assets/{$asset->id}", $this->payload(['assignee_type' => 'user', 'assignee_id' => $other->id]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->actor())
            ->put("/app/assets/{$asset->id}", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertTrue($asset->fresh()->assignee->is($owner));
    }

    public function test_asset_tag_is_unique_among_assets_that_are_not_deleted(): void
    {
        $taken = Asset::factory()->create(['asset_tag' => 'IT-1']);
        $deleted = Asset::factory()->create(['asset_tag' => 'IT-2']);
        $deleted->delete();

        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['asset_tag' => 'IT-1']))
            ->assertSessionHasErrors('asset_tag');
        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['asset_tag' => 'IT-2']))
            ->assertSessionHasNoErrors();
        // Keeping its own tag on update is fine.
        $this->actingAs($this->actor())->put("/app/assets/{$taken->id}", $this->payload(['asset_tag' => 'IT-1']))
            ->assertSessionHasNoErrors();
    }

    public function test_image_is_stored_served_and_removed(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();

        $this->actingAs($this->actor())
            ->post("/app/assets/{$asset->id}", $this->payload(['_method' => 'put', 'image' => UploadedFile::fake()->image('a.png')]))
            ->assertSessionHasNoErrors();
        $path = $asset->fresh()->image;
        Storage::disk()->assertExists($path);
        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}/image")->assertOk();

        $this->actingAs($this->actor())
            ->post("/app/assets/{$asset->id}", $this->payload(['_method' => 'put', 'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')]))
            ->assertSessionHasErrors('image');

        $this->actingAs($this->actor())
            ->put("/app/assets/{$asset->id}", $this->payload(['remove_image' => true]))
            ->assertSessionHasNoErrors();
        $this->assertNull($asset->fresh()->image);
        Storage::disk()->assertMissing($path);
    }

    public function test_image_and_counters_are_not_mass_assignable(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}", $this->payload([
            'image' => 'other/file.png', 'checkout_counter' => 99, 'last_checkout' => '2020-01-01',
        ]));

        $asset->refresh();
        $this->assertNotSame('other/file.png', $asset->image);
        $this->assertSame(0, $asset->checkout_counter);
        $this->assertNull($asset->last_checkout);
    }

    public function test_destroy_soft_deletes_and_handovers_still_list_the_asset(): void
    {
        $asset = Asset::factory()->create();
        $handover = Handover::factory()->create();
        $handover->assets()->attach($asset->id, ['id' => (string) Str::uuid()]);

        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');

        $this->assertSoftDeleted($asset);
        $this->assertNotNull($asset->fresh()->deleted_by);
        $this->assertCount(1, $handover->fresh()->assets);
        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")->assertNotFound();
    }

    public function test_eol_date_follows_the_model_unless_set_by_hand(): void
    {
        $model = AssetModel::factory()->create(['eol' => 36]);

        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['name' => 'Auto', 'model_id' => $model->id, 'purchase_date' => '2024-01-31']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2027-01-31', Asset::firstWhere('name', 'Auto')->asset_eol_date->toDateString());

        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['name' => 'Manual', 'model_id' => $model->id, 'purchase_date' => '2024-01-31',
                'eol_explicit' => true, 'asset_eol_date' => '2025-06-30']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2025-06-30', Asset::firstWhere('name', 'Manual')->asset_eol_date->toDateString());

        $this->actingAs($this->actor())
            ->post('/app/assets', $this->payload(['eol_explicit' => true]))
            ->assertSessionHasErrors('asset_eol_date');
    }

    public function test_creator_is_stamped(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/app/assets', $this->payload(['name' => 'Stamped']))->assertSessionHasNoErrors();

        $this->assertSame($actor->id, Asset::firstWhere('name', 'Stamped')->created_by);
    }
}
