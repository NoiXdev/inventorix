<?php

namespace Tests\Feature\App;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
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
            'name' => 'Laptops',
            'category_type' => 'asset',
            'eula_text' => 'Handle with care.',
            'use_default_eula' => '0',
            'require_acceptance' => '1',
            'alert_on_response' => '1',
            'checkin_email' => '0',
            'notes' => 'Company notebooks.',
            ...$overrides,
        ];
    }

    public function test_index_lists_categories(): void
    {
        Category::factory()->count(3)->create();
        $this->actingAs($this->user)->get('/app/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('categories/index')
                ->has('categories.data', 3)
                ->where('categories.data.0.category_type_label', 'Asset')
                ->has('typeOptions', 5));
    }

    public function test_index_filters_by_type(): void
    {
        Category::factory()->create(['category_type' => CategoryType::ASSET]);
        Category::factory()->create(['category_type' => CategoryType::LICENSE, 'name' => 'Software']);
        $this->actingAs($this->user)->get('/app/categories?filter[category_type]=license')
            ->assertInertia(fn (Assert $p) => $p->has('categories.data', 1)->where('categories.data.0.name', 'Software'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/categories')->assertRedirect();
    }

    public function test_store_creates_a_category_and_stamps_the_creator(): void
    {
        $this->actingAs($this->user)->post('/app/categories', $this->payload())
            ->assertRedirect('/app/categories')
            ->assertSessionHasNoErrors();

        $c = Category::sole();
        $this->assertSame('Laptops', $c->name);
        $this->assertSame(CategoryType::ASSET, $c->category_type);
        $this->assertSame('Handle with care.', $c->eula_text);
        $this->assertFalse($c->use_default_eula);
        $this->assertTrue($c->require_acceptance);
        $this->assertTrue($c->alert_on_response);
        $this->assertFalse($c->checkin_email);
        $this->assertSame('Company notebooks.', $c->notes);
        $this->assertSame($this->user->id, $c->created_by);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user)->post('/app/categories', $this->payload([
            'name' => '', 'category_type' => 'bogus', 'require_acceptance' => 'maybe',
        ]))->assertSessionHasErrors(['name', 'category_type', 'require_acceptance']);
    }

    public function test_image_must_not_be_svg(): void
    {
        $this->actingAs($this->user)->post('/app/categories', $this->payload([
            'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_store_saves_an_uploaded_image_and_serves_it(): void
    {
        $this->actingAs($this->user)->post('/app/categories', $this->payload([
            'image' => UploadedFile::fake()->image('cat.png'),
        ]))->assertSessionHasNoErrors();

        $c = Category::sole();
        $this->assertNotNull($c->image);
        Storage::assertExists($c->image);
        $this->actingAs($this->user)->get("/app/categories/{$c->id}/image")->assertOk();
    }

    public function test_image_is_not_mass_assignable(): void
    {
        $this->actingAs($this->user)->post('/app/categories', $this->payload(['image' => 'secret/file.pdf']))
            ->assertSessionHasErrors('image');
        $this->assertNull(Category::first());
    }

    public function test_update_replaces_and_removes_the_image(): void
    {
        $c = Category::factory()->create();
        $this->actingAs($this->user)->put("/app/categories/{$c->id}", $this->payload([
            'image' => UploadedFile::fake()->image('a.png'),
        ]));
        $first = $c->fresh()->image;
        Storage::assertExists($first);

        $this->actingAs($this->user)->put("/app/categories/{$c->id}", $this->payload(['remove_image' => '1']))
            ->assertRedirect('/app/categories');
        Storage::assertMissing($first);
        $this->assertNull($c->fresh()->image);
    }

    public function test_edit_renders_the_category(): void
    {
        $c = Category::factory()->create(['name' => 'Monitore', 'category_type' => CategoryType::ACCESSORY]);
        $this->actingAs($this->user)->get("/app/categories/{$c->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('categories/edit')
                ->where('category.name', 'Monitore')
                ->where('category.category_type', 'accessory')
                ->where('category.require_acceptance', false)
                ->where('category.image_url', null));
    }

    public function test_update_changes_a_category(): void
    {
        $c = Category::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user)->put("/app/categories/{$c->id}", $this->payload(['name' => 'New', 'checkin_email' => '1']))
            ->assertRedirect('/app/categories');
        $fresh = $c->fresh();
        $this->assertSame('New', $fresh->name);
        $this->assertTrue($fresh->checkin_email);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $c = Category::factory()->create();
        $this->actingAs($this->user)->delete("/app/categories/{$c->id}")
            ->assertRedirect('/app/categories');
        $this->assertSoftDeleted($c);
        $this->assertSame($this->user->id, Category::withTrashed()->find($c->id)->deleted_by);
    }
}
