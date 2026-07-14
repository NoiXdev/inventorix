<?php

namespace Tests\Feature\App;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManufacturerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
    }

    public function test_lists_manufacturers_with_counts(): void
    {
        Manufacturer::factory()->count(3)->create();

        $this->actingAs($this->user)->get('/app/manufacturers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // shouldExist: false — the React page itself lands in Task 9;
                // this test only exercises the controller/route/props contract.
                ->component('manufacturers/index', false)
                ->has('manufacturers.data', 3)
                ->has('manufacturers.data.0', fn (Assert $row) => $row
                    ->has('id')->has('name')->has('models_count')->has('assets_count')
                )
            );
    }

    public function test_requires_authentication(): void
    {
        $this->get('/app/manufacturers')->assertRedirect();
    }

    public function test_stores_a_manufacturer(): void
    {
        $this->actingAs($this->user)
            ->post('/app/manufacturers', ['name' => 'Acme'])
            ->assertRedirect('/app/manufacturers');

        $this->assertTrue(Manufacturer::where('name', 'Acme')->exists());
    }

    public function test_validates_that_name_is_required(): void
    {
        $this->actingAs($this->user)
            ->post('/app/manufacturers', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_updates_a_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Old']);

        $this->actingAs($this->user)
            ->put("/app/manufacturers/{$manufacturer->id}", ['name' => 'New'])
            ->assertRedirect('/app/manufacturers');

        $this->assertSame('New', $manufacturer->fresh()->name);
    }

    public function test_deletes_a_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAs($this->user)
            ->delete("/app/manufacturers/{$manufacturer->id}")
            ->assertRedirect('/app/manufacturers');

        $this->assertNull(Manufacturer::find($manufacturer->id));
    }
}
