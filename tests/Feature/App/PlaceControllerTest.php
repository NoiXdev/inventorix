<?php

namespace Tests\Feature\App;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_places(): void
    {
        Place::factory()->count(3)->create();
        $this->actingAs($this->user())->get('/app/places')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('places/index')->has('places.data', 3));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/places')->assertRedirect();
    }

    public function test_store_creates_a_place(): void
    {
        $this->actingAs($this->user())->post('/app/places', ['name' => 'Berlin'])
            ->assertRedirect('/app/places');
        $this->assertTrue(Place::where('name', 'Berlin')->exists());
    }

    public function test_store_validates_name_required(): void
    {
        $this->actingAs($this->user())->post('/app/places', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_changes_a_place(): void
    {
        $p = Place::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user())->put("/app/places/{$p->id}", ['name' => 'New'])
            ->assertRedirect('/app/places');
        $this->assertSame('New', $p->fresh()->name);
    }

    public function test_destroy_deletes_a_place(): void
    {
        $p = Place::factory()->create();
        $this->actingAs($this->user())->delete("/app/places/{$p->id}")
            ->assertRedirect('/app/places');
        $this->assertNull(Place::find($p->id));
    }
}
