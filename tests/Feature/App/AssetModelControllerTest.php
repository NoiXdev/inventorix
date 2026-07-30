<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetModelControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_models_with_manufacturer_name_and_assets_count(): void
    {
        $m = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $m->id]);
        Asset::factory()->count(2)->create(['model_id' => $model->id]);

        $this->actingAs($this->user())->get('/app/asset-models')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('asset-models/index')
                ->has('assetModels.data', 1, fn (Assert $row) => $row
                    ->where('name', 'X1')
                    ->where('manufacturer_name', 'Acme')
                    ->where('assets_count', 2)
                    ->etc())
                ->has('manufacturerOptions'));
    }

    public function test_index_filters_by_manufacturer(): void
    {
        $a = Manufacturer::factory()->create();
        $b = Manufacturer::factory()->create();
        AssetModel::factory()->count(2)->create(['manufacturer_id' => $a->id]);
        AssetModel::factory()->create(['manufacturer_id' => $b->id]);

        $this->actingAs($this->user())->get("/app/asset-models?filter[manufacturer_id]={$a->id}")
            ->assertInertia(fn (Assert $p) => $p->has('assetModels.data', 2));
    }

    public function test_index_sorts_by_manufacturer_name(): void
    {
        // Model-name order is the OPPOSITE of manufacturer-name order so the two
        // sort criteria disagree: Zeta's model is named 'aaa' (would sort first
        // by its own name), Alpha's model is named 'zzz' (would sort last by its
        // own name). If `sort=manufacturer_name` silently fell back to sorting by
        // the model's own name, 'aaa' (manufacturer Zeta) would appear first —
        // failing the assertion below, which expects Alpha (model 'zzz') first.
        $zeta = Manufacturer::factory()->create(['name' => 'Zeta']);
        $alpha = Manufacturer::factory()->create(['name' => 'Alpha']);
        AssetModel::factory()->create(['name' => 'aaa', 'manufacturer_id' => $zeta->id]);
        AssetModel::factory()->create(['name' => 'zzz', 'manufacturer_id' => $alpha->id]);

        $this->actingAs($this->user())->get('/app/asset-models?sort=manufacturer_name')
            ->assertInertia(fn (Assert $p) => $p
                ->where('assetModels.data.0.manufacturer_name', 'Alpha')
                ->where('assetModels.data.0.name', 'zzz'));
    }

    public function test_index_searches_by_manufacturer_name(): void
    {
        $acme = Manufacturer::factory()->create(['name' => 'Acme']);
        $globex = Manufacturer::factory()->create(['name' => 'Globex']);
        AssetModel::factory()->create(['name' => 'aaa', 'manufacturer_id' => $acme->id]);
        AssetModel::factory()->create(['name' => 'bbb', 'manufacturer_id' => $globex->id]);

        $this->actingAs($this->user())->get('/app/asset-models?search=Acme')
            ->assertInertia(fn (Assert $p) => $p->has('assetModels.data', 1)
                ->where('assetModels.data.0.manufacturer_name', 'Acme'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/asset-models')->assertRedirect();
    }

    public function test_store_creates_a_model(): void
    {
        $m = Manufacturer::factory()->create();
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => 'X2', 'manufacturer_id' => $m->id])
            ->assertRedirect('/app/asset-models');
        $this->assertTrue(AssetModel::where('name', 'X2')->where('manufacturer_id', $m->id)->exists());
    }

    public function test_store_validates_manufacturer_exists(): void
    {
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => 'X2', 'manufacturer_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertSessionHasErrors('manufacturer_id');
    }

    public function test_store_validates_name_required(): void
    {
        $m = Manufacturer::factory()->create();
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => '', 'manufacturer_id' => $m->id])
            ->assertSessionHasErrors('name');
    }

    public function test_update_changes_a_model(): void
    {
        $m = Manufacturer::factory()->create();
        $model = AssetModel::factory()->create(['manufacturer_id' => $m->id]);
        $m2 = Manufacturer::factory()->create();
        $this->actingAs($this->user())->put("/app/asset-models/{$model->id}", ['name' => 'Renamed', 'manufacturer_id' => $m2->id])
            ->assertRedirect('/app/asset-models');
        $this->assertSame('Renamed', $model->fresh()->name);
        $this->assertSame($m2->id, $model->fresh()->manufacturer_id);
    }

    public function test_destroy_deletes_a_model(): void
    {
        $model = AssetModel::factory()->create();
        $this->actingAs($this->user())->delete("/app/asset-models/{$model->id}")
            ->assertRedirect('/app/asset-models');
        $this->assertNull(AssetModel::find($model->id));
    }
}
