<?php

namespace Tests\Feature\App;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
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
        return [
            'name' => 'Bechtle AG',
            'address' => 'Bechtle Platz 1',
            'address2' => '',
            'city' => 'Neckarsulm',
            'state' => 'Baden-Württemberg',
            'zip' => '74172',
            'country' => 'DE',
            'phone' => '+49 7132 981-0',
            'fax' => '',
            'email' => 'sales@example.com',
            'contact' => 'Max Mustermann',
            'url' => 'https://www.example.com',
            'notes' => 'Main hardware supplier.',
            'remove_image' => '0',
            ...$overrides,
        ];
    }

    public function test_index_lists_suppliers_with_country_names(): void
    {
        Supplier::factory()->create(['name' => 'A', 'country' => 'DE']);
        Supplier::factory()->count(2)->create();

        $this->actingAs($this->user)->get('/app/suppliers?sort=name')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('suppliers/index')
                ->has('suppliers.data', 3)
                ->where('suppliers.data.0.country', 'DE')
                ->where('suppliers.data.0.country_name', fn (string $n) => in_array($n, ['Germany', 'Deutschland'], true))
                // Only the countries in use are offered as a filter.
                ->has('countryOptions', 1));
    }

    public function test_index_searches_and_filters_by_country(): void
    {
        Supplier::factory()->create(['name' => 'Alpha', 'country' => 'DE', 'city' => 'Köln']);
        Supplier::factory()->create(['name' => 'Beta', 'country' => 'AT', 'city' => 'Wien']);

        $this->actingAs($this->user)->get('/app/suppliers?filter[country]=AT')
            ->assertInertia(fn (Assert $p) => $p->has('suppliers.data', 1)->where('suppliers.data.0.name', 'Beta'));
        $this->actingAs($this->user)->get('/app/suppliers?search=Köln')
            ->assertInertia(fn (Assert $p) => $p->has('suppliers.data', 1)->where('suppliers.data.0.name', 'Alpha'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/suppliers')->assertRedirect();
    }

    public function test_store_creates_a_supplier_and_stamps_the_creator(): void
    {
        $this->actingAs($this->user)->post('/app/suppliers', $this->payload())
            ->assertRedirect('/app/suppliers')
            ->assertSessionHasNoErrors();

        $s = Supplier::sole();
        $this->assertSame('Bechtle AG', $s->name);
        $this->assertSame('DE', $s->country);
        $this->assertSame('74172', $s->zip);
        $this->assertSame('sales@example.com', $s->email);
        $this->assertSame('https://www.example.com', $s->url);
        $this->assertNull($s->fax); // empty strings become null
        $this->assertSame($this->user->id, $s->created_by);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user)->post('/app/suppliers', $this->payload([
            'name' => '',
            'country' => 'XX',
            'email' => 'not-an-email',
            'url' => 'javascript:alert(1)',
            'zip' => str_repeat('1', 11),
            'phone' => str_repeat('1', 36),
        ]))->assertSessionHasErrors(['name', 'country', 'email', 'url', 'zip', 'phone']);
    }

    public function test_image_must_not_be_svg(): void
    {
        $this->actingAs($this->user)->post('/app/suppliers', $this->payload([
            'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_image_is_not_mass_assignable(): void
    {
        $this->actingAs($this->user)->post('/app/suppliers', $this->payload(['image' => 'secret/file.pdf']))
            ->assertSessionHasErrors('image');
        $this->assertNull(Supplier::first());
    }

    public function test_store_saves_an_uploaded_image_and_serves_it(): void
    {
        $this->actingAs($this->user)->post('/app/suppliers', $this->payload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]))->assertSessionHasNoErrors();

        $s = Supplier::sole();
        Storage::assertExists($s->image);
        $this->actingAs($this->user)->get("/app/suppliers/{$s->id}/image")->assertOk();
    }

    public function test_update_replaces_and_removes_the_image(): void
    {
        $s = Supplier::factory()->create();
        $this->actingAs($this->user)->put("/app/suppliers/{$s->id}", $this->payload([
            'image' => UploadedFile::fake()->image('a.png'),
        ]));
        $first = $s->fresh()->image;
        Storage::assertExists($first);

        $this->actingAs($this->user)->put("/app/suppliers/{$s->id}", $this->payload(['remove_image' => '1']))
            ->assertRedirect('/app/suppliers');
        Storage::assertMissing($first);
        $this->assertNull($s->fresh()->image);
    }

    public function test_edit_renders_the_supplier(): void
    {
        $s = Supplier::factory()->create(['name' => 'Cancom', 'country' => 'DE', 'contact' => 'Erika']);
        $this->actingAs($this->user)->get("/app/suppliers/{$s->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('suppliers/edit')
                ->where('supplier.name', 'Cancom')
                ->where('supplier.country', 'DE')
                ->where('supplier.contact', 'Erika')
                ->where('supplier.image_url', null)
                ->has('countryOptions', 249));
    }

    public function test_update_changes_a_supplier(): void
    {
        $s = Supplier::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user)->put("/app/suppliers/{$s->id}", $this->payload(['name' => 'New', 'country' => '']))
            ->assertRedirect('/app/suppliers');
        $fresh = $s->fresh();
        $this->assertSame('New', $fresh->name);
        $this->assertNull($fresh->country);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $s = Supplier::factory()->create();
        $this->actingAs($this->user)->delete("/app/suppliers/{$s->id}")
            ->assertRedirect('/app/suppliers');
        $this->assertSoftDeleted($s);
        $this->assertSame($this->user->id, Supplier::withTrashed()->find($s->id)->deleted_by);
    }
}
