<?php

namespace Tests\Feature\App;

use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_asset_types(): void
    {
        AssetType::factory()->count(3)->create();
        $this->actingAs($this->user())->get('/app/asset-types')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('asset-types/index')->has('assetTypes.data', 3));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/asset-types')->assertRedirect();
    }

    public function test_store_creates_an_asset_type(): void
    {
        $this->actingAs($this->user())->post('/app/asset-types', ['name' => 'Laptop'])
            ->assertRedirect('/app/asset-types');
        $this->assertTrue(AssetType::where('name', 'Laptop')->exists());
    }

    public function test_store_validates_name_required(): void
    {
        $this->actingAs($this->user())->post('/app/asset-types', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_changes_an_asset_type(): void
    {
        $a = AssetType::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user())->put("/app/asset-types/{$a->id}", ['name' => 'New'])
            ->assertRedirect('/app/asset-types');
        $this->assertSame('New', $a->fresh()->name);
    }

    public function test_destroy_deletes_an_asset_type(): void
    {
        $a = AssetType::factory()->create();
        $this->actingAs($this->user())->delete("/app/asset-types/{$a->id}")
            ->assertRedirect('/app/asset-types');
        $this->assertNull(AssetType::find($a->id));
    }
}
