<?php

namespace Tests\Feature\App;

use App\Enums\CategoryType;
use App\Models\Accessory;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->superuser()->create(['login_enabled' => true]);
        Storage::fake();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'USB-C Charger',
            'category_id' => Category::factory()->create(['category_type' => CategoryType::ACCESSORY])->id,
            'manufacturer_id' => Manufacturer::factory()->create()->id,
            'model_number' => 'PD-65W',
            'location_id' => Location::factory()->create()->id,
            'default_supplier_id' => Supplier::factory()->create()->id,
            'qty' => '12',
            'min_amt' => '3',
            'default_purchase_cost' => '39.90',
            'requestable' => '1',
            'archived' => '0',
            'notes' => 'For notebooks.',
            'remove_image' => '0',
            ...$overrides,
        ];
    }

    public function test_index_lists_accessories_with_relations_and_low_stock(): void
    {
        $category = Category::factory()->create(['name' => 'Kabel', 'category_type' => CategoryType::ACCESSORY]);
        Accessory::factory()->create(['name' => 'A', 'qty' => 2, 'min_amt' => 2, 'category_id' => $category->id]);
        Accessory::factory()->create(['name' => 'B', 'qty' => 5, 'min_amt' => 2]);
        Accessory::factory()->create(['name' => 'C', 'qty' => 0, 'min_amt' => null]);

        $this->actingAs($this->user)->get('/app/accessories?sort=name')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('accessories/index')
                ->has('accessories.data', 3)
                ->where('accessories.data.0.category_name', 'Kabel')
                ->where('accessories.data.0.low_stock', true)
                ->where('accessories.data.1.low_stock', false)
                ->where('accessories.data.2.low_stock', false)
                ->has('categoryOptions', 1));
    }

    public function test_index_filters_by_archived_and_category(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::ACCESSORY]);
        Accessory::factory()->create(['name' => 'Old', 'archived' => true]);
        Accessory::factory()->create(['name' => 'Current', 'category_id' => $category->id]);

        $this->actingAs($this->user)->get('/app/accessories?filter[archived]=1')
            ->assertInertia(fn (Assert $p) => $p->has('accessories.data', 1)->where('accessories.data.0.name', 'Old'));
        $this->actingAs($this->user)->get('/app/accessories?filter[category_id]='.$category->id)
            ->assertInertia(fn (Assert $p) => $p->has('accessories.data', 1)->where('accessories.data.0.name', 'Current'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/accessories')->assertRedirect();
    }

    public function test_store_creates_an_accessory_and_stamps_the_creator(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->user)->post('/app/accessories', $payload)
            ->assertRedirect('/app/accessories')
            ->assertSessionHasNoErrors();

        $a = Accessory::sole();
        $this->assertSame('USB-C Charger', $a->name);
        $this->assertSame(12, $a->qty);
        $this->assertSame(3, $a->min_amt);
        $this->assertSame('39.90', $a->default_purchase_cost);
        $this->assertTrue($a->requestable);
        $this->assertFalse($a->archived);
        $this->assertSame($payload['category_id'], $a->category_id);
        $this->assertSame($payload['location_id'], $a->location_id);
        $this->assertSame($payload['default_supplier_id'], $a->defaultSupplier->id);
        $this->assertSame($this->user->id, $a->created_by);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user)->post('/app/accessories', $this->payload([
            'name' => '',
            'qty' => '-1',
            'min_amt' => '1.5',
            'default_purchase_cost' => '1.999',
            'requestable' => 'maybe',
            'location_id' => 'not-a-uuid',
        ]))->assertSessionHasErrors(['name', 'qty', 'min_amt', 'default_purchase_cost', 'requestable', 'location_id']);
    }

    public function test_only_accessory_categories_are_accepted(): void
    {
        $assetCategory = Category::factory()->create(['category_type' => CategoryType::ASSET]);

        $this->actingAs($this->user)->post('/app/accessories', $this->payload(['category_id' => $assetCategory->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_new_links_to_trashed_records_are_refused_but_existing_ones_kept(): void
    {
        $location = Location::factory()->create();
        $location->delete();

        $this->actingAs($this->user)->post('/app/accessories', $this->payload(['location_id' => $location->id]))
            ->assertSessionHasErrors('location_id');

        // An accessory already stored there can still be saved without moving it.
        $accessory = Accessory::factory()->create(['location_id' => $location->id]);
        $this->actingAs($this->user)->put("/app/accessories/{$accessory->id}", $this->payload(['location_id' => $location->id]))
            ->assertSessionHasNoErrors();

        // ...and the edit form still offers it.
        $this->actingAs($this->user)->get("/app/accessories/{$accessory->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('locationOptions', fn ($o) => collect($o)->contains('value', $location->id)));
    }

    public function test_image_must_not_be_svg_and_is_not_mass_assignable(): void
    {
        $this->actingAs($this->user)->post('/app/accessories', $this->payload([
            'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($this->user)->post('/app/accessories', $this->payload(['image' => 'secret/file.pdf']))
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Accessory::count());
    }

    public function test_image_is_stored_served_and_removed(): void
    {
        $this->actingAs($this->user)->post('/app/accessories', $this->payload([
            'image' => UploadedFile::fake()->image('a.png'),
        ]))->assertSessionHasNoErrors();

        $a = Accessory::sole();
        $first = $a->image;
        Storage::assertExists($first);
        $this->actingAs($this->user)->get("/app/accessories/{$a->id}/image")->assertOk();

        $this->actingAs($this->user)->put("/app/accessories/{$a->id}", $this->payload(['remove_image' => '1']))
            ->assertRedirect('/app/accessories');
        Storage::assertMissing($first);
        $this->assertNull($a->fresh()->image);
    }

    public function test_edit_renders_the_accessory(): void
    {
        $a = Accessory::factory()->create(['name' => 'Maus', 'qty' => 7, 'default_purchase_cost' => '12.5']);
        $this->actingAs($this->user)->get("/app/accessories/{$a->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('accessories/edit')
                ->where('accessory.name', 'Maus')
                ->where('accessory.qty', 7)
                ->where('accessory.default_purchase_cost', '12.50')
                ->where('accessory.image_url', null)
                ->has('categoryOptions')->has('manufacturerOptions')->has('supplierOptions')->has('locationOptions'));
    }

    public function test_update_changes_an_accessory(): void
    {
        $a = Accessory::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user)->put("/app/accessories/{$a->id}", $this->payload([
            'name' => 'New', 'archived' => '1', 'min_amt' => '', 'location_id' => '',
        ]))->assertRedirect('/app/accessories');

        $fresh = $a->fresh();
        $this->assertSame('New', $fresh->name);
        $this->assertTrue($fresh->archived);
        $this->assertNull($fresh->min_amt);
        $this->assertNull($fresh->location_id);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $a = Accessory::factory()->create();
        $this->actingAs($this->user)->delete("/app/accessories/{$a->id}")
            ->assertRedirect('/app/accessories');
        $this->assertSoftDeleted($a);
        $this->assertSame($this->user->id, Accessory::withTrashed()->find($a->id)->deleted_by);
    }
}
