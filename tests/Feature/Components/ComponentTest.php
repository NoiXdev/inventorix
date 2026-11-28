<?php

namespace Tests\Feature\Components;

use App\Enums\CategoryType;
use App\Enums\Permission;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentAsset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComponentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superuser()->create();
    }

    public function test_create_with_all_fields(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::COMPONENT]);
        $location = Location::factory()->create();

        $this->actingAs($this->admin())->post('/app/components', [
            'name' => 'RAM 16GB', 'category_id' => $category->id, 'model_number' => 'KVR', 'serial' => 'SN-1',
            'location_id' => $location->id, 'order_number' => 'PO-9', 'purchase_date' => '2026-10-01',
            'purchase_cost' => '49.90', 'qty' => 8, 'min_amt' => 2, 'notes' => 'DDR5',
        ])->assertRedirect('/app/components')->assertSessionHasNoErrors();

        $component = Component::query()->sole();
        $this->assertSame('SN-1', $component->serial);
        $this->assertSame('2026-10-01', $component->purchase_date->toDateString());
        $this->assertSame('49.90', $component->purchase_cost);
        $this->assertSame(8, $component->qty);
        $this->assertSame($category->id, $component->category_id);
    }

    public function test_only_component_categories(): void
    {
        $assetCategory = Category::factory()->create(['category_type' => CategoryType::ASSET]);

        $this->actingAs($this->admin())->post('/app/components', ['name' => 'X', 'qty' => 1, 'category_id' => $assetCategory->id])
            ->assertSessionHasErrors('category_id');
    }

    public function test_update(): void
    {
        $component = Component::factory()->create(['qty' => 5]);

        $this->actingAs($this->admin())->put("/app/components/{$component->id}", ['name' => 'Renamed', 'qty' => 9])
            ->assertRedirect('/app/components')->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $component->refresh()->name);
        $this->assertSame(9, $component->qty);
    }

    public function test_index_rows(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::COMPONENT, 'name' => 'Memory']);
        $component = Component::factory()->create(['name' => 'RAM', 'serial' => 'S1', 'qty' => 5, 'min_amt' => 4, 'category_id' => $category->id]);
        ComponentAsset::create(['component_id' => $component->id, 'asset_id' => Asset::factory()->create()->id, 'assigned_qty' => 2, 'checked_out_at' => now()]);

        $this->actingAs($this->admin())->get('/app/components')
            ->assertInertia(fn (Assert $p) => $p->component('components/index')
                ->where('components.data.0.name', 'RAM')
                ->where('components.data.0.category_name', 'Memory')
                ->where('components.data.0.serial', 'S1')
                ->where('components.data.0.location_name', null)
                ->where('components.data.0.qty', 5)
                ->where('components.data.0.remaining', 3)
                ->where('components.data.0.low_stock', true));
    }

    public function test_index_needs_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/app/components')->assertForbidden();
    }

    public function test_qty_cannot_drop_below_units_checked_out(): void
    {
        $component = Component::factory()->create(['qty' => 5]);
        ComponentAsset::create(['component_id' => $component->id, 'asset_id' => Asset::factory()->create()->id, 'assigned_qty' => 3, 'checked_out_at' => now()]);

        $this->actingAs($this->admin())->put("/app/components/{$component->id}", ['name' => $component->name, 'qty' => 2])
            ->assertSessionHasErrors('qty');
        $this->assertSame(5, $component->refresh()->qty);
    }

    public function test_destroy_is_blocked_while_units_are_out(): void
    {
        $component = Component::factory()->create();
        ComponentAsset::create(['component_id' => $component->id, 'asset_id' => Asset::factory()->create()->id, 'assigned_qty' => 1, 'checked_out_at' => now()]);

        $this->actingAs($this->admin())->delete("/app/components/{$component->id}")
            ->assertSessionHas('error', 'Check every unit in before deleting the component.');
        $this->assertNotSoftDeleted($component);
    }

    public function test_destroy_soft_deletes(): void
    {
        $component = Component::factory()->create();

        $this->actingAs($this->admin())->delete("/app/components/{$component->id}")->assertRedirect('/app/components');
        $this->assertSoftDeleted($component);
    }

    public function test_show_page_renders(): void
    {
        $component = Component::factory()->create();

        $this->actingAs($this->admin())->get("/app/components/{$component->id}")
            ->assertInertia(fn (Assert $p) => $p->component('components/show')->where('component.id', $component->id));
    }

    public function test_catalogue_has_components_after_consumables(): void
    {
        $sections = array_column(Permission::catalogue(), 'section');
        $this->assertSame(array_search('consumables', $sections) + 1, array_search('components', $sections));
    }
}
