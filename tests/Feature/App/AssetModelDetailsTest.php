<?php

namespace Tests\Feature\App;

use App\Enums\CategoryType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomFieldset;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetModelDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
        Storage::fake();
    }

    private function payload(array $overrides = []): array
    {
        // Booleans arrive as '1'/'0' strings, as the form sends them via FormData.
        return [
            'name' => 'ThinkPad T14',
            'manufacturer_id' => Manufacturer::factory()->create()->id,
            'model_number' => '20W0-00A',
            'category_id' => Category::factory()->create()->id,
            'depreciation_id' => Depreciation::factory()->create()->id,
            'fieldset_id' => CustomFieldset::factory()->create()->id,
            'eol' => 48,
            'min_amt' => 2,
            'require_serial' => '1',
            'requestable' => '0',
            'notes' => 'Standard developer laptop.',
            ...$overrides,
        ];
    }

    public function test_store_persists_every_field_and_relation(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->user)->post('/app/asset-models', $payload)
            ->assertRedirect('/app/asset-models')
            ->assertSessionHasNoErrors();

        $m = AssetModel::sole();
        $this->assertSame('20W0-00A', $m->model_number);
        $this->assertSame(48, $m->eol);
        $this->assertSame(2, $m->min_amt);
        $this->assertTrue($m->require_serial);
        $this->assertFalse($m->requestable);
        $this->assertSame('Standard developer laptop.', $m->notes);
        $this->assertSame($payload['category_id'], $m->category->id);
        $this->assertSame($payload['depreciation_id'], $m->depreciation->id);
        $this->assertSame($payload['fieldset_id'], $m->fieldset->id);
        $this->assertSame($this->user->id, $m->created_by);
        $this->assertTrue($m->category->models->contains($m));
        $this->assertTrue($m->depreciation->models->contains($m));
        $this->assertTrue($m->fieldset->models->contains($m));
    }

    public function test_the_new_fields_are_optional(): void
    {
        $this->actingAs($this->user)->post('/app/asset-models', [
            'name' => 'Bare', 'manufacturer_id' => Manufacturer::factory()->create()->id,
            'category_id' => '', 'depreciation_id' => '', 'fieldset_id' => '', 'eol' => '', 'min_amt' => '',
        ])->assertSessionHasNoErrors();

        $m = AssetModel::sole();
        $this->assertNull($m->category_id);
        $this->assertNull($m->eol);
        $this->assertFalse($m->require_serial);
    }

    public function test_only_asset_categories_are_accepted_and_offered(): void
    {
        $license = Category::factory()->create(['category_type' => CategoryType::LICENSE]);
        $asset = Category::factory()->create(['category_type' => CategoryType::ASSET]);

        $this->actingAs($this->user)->post('/app/asset-models', $this->payload(['category_id' => $license->id]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($this->user)->get('/app/asset-models/create')
            ->assertInertia(fn (Assert $p) => $p
                ->where('categoryOptions', fn ($options) => collect($options)->pluck('value')->contains($asset->id)
                    && ! collect($options)->pluck('value')->contains($license->id))
                ->has('depreciationOptions')
                ->has('fieldsetOptions'));
    }

    public function test_trashed_relations_are_rejected(): void
    {
        $depreciation = Depreciation::factory()->create();
        $depreciation->delete();

        $this->actingAs($this->user)->post('/app/asset-models', $this->payload(['depreciation_id' => $depreciation->id, 'eol' => 0]))
            ->assertSessionHasErrors(['depreciation_id', 'eol']);
    }

    public function test_image_upload_replace_and_remove(): void
    {
        $this->actingAs($this->user)->post('/app/asset-models', $this->payload([
            'image' => UploadedFile::fake()->image('t14.png'),
        ]))->assertSessionHasNoErrors();

        $m = AssetModel::sole();
        Storage::assertExists($m->image);
        $this->actingAs($this->user)->get("/app/asset-models/{$m->id}/image")->assertOk();

        $first = $m->image;
        $this->actingAs($this->user)->put("/app/asset-models/{$m->id}", $this->payload(['remove_image' => '1']));
        Storage::assertMissing($first);
        $this->assertNull($m->fresh()->image);
    }

    public function test_image_rejects_svg_and_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->user)->post('/app/asset-models', $this->payload([
            'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($this->user)->post('/app/asset-models', $this->payload(['image' => 'secret/file.pdf']))
            ->assertSessionHasErrors('image');
        $this->assertNull(AssetModel::first());
    }

    public function test_edit_and_index_expose_the_new_fields(): void
    {
        $category = Category::factory()->create(['name' => 'Laptops']);
        $m = AssetModel::factory()->create(['model_number' => 'ABC-1', 'category_id' => $category->id, 'eol' => 36]);

        $this->actingAs($this->user)->get("/app/asset-models/{$m->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->component('asset-models/edit')
                ->where('assetModel.model_number', 'ABC-1')
                ->where('assetModel.category_id', $category->id)
                ->where('assetModel.eol', 36)
                ->where('assetModel.image_url', null));

        $this->actingAs($this->user)->get("/app/asset-models?filter[category_id]={$category->id}")
            ->assertInertia(fn (Assert $p) => $p->has('assetModels.data', 1)
                ->where('assetModels.data.0.category_name', 'Laptops')
                ->where('assetModels.data.0.model_number', 'ABC-1'));
    }

    public function test_destroy_soft_deletes_and_keeps_assets_and_history_pointing_at_it(): void
    {
        $m = AssetModel::factory()->create(['name' => 'Gone']);
        $asset = Asset::factory()->create(['model_id' => $m->id]);

        $this->actingAs($this->user)->delete("/app/asset-models/{$m->id}")
            ->assertRedirect('/app/asset-models');

        $this->assertSoftDeleted($m);
        $this->assertSame($this->user->id, AssetModel::withTrashed()->find($m->id)->deleted_by);
        $this->assertSame($m->id, $asset->fresh()->model_id);
    }
}
