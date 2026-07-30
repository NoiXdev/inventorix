<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_people_with_asset_counts(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->get('/app/people')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a->component('people/index')
                ->has('people.data', 1)
                ->where('people.data.0.assets_count', 2)->etc());
    }

    public function test_store_derives_name(): void
    {
        $this->actingAs($this->actor())->post('/app/people', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@x.de',
        ])->assertRedirect();

        $this->assertDatabaseHas('people', ['name' => 'Ada Lovelace', 'email' => 'ada@x.de']);
    }

    public function test_store_validation(): void
    {
        $this->actingAs($this->actor())->post('/app/people', ['firstname' => '', 'lastname' => '', 'email' => 'nope'])
            ->assertSessionHasErrors(['firstname', 'lastname', 'email']);
    }

    public function test_update_derives_name(): void
    {
        $p = Person::factory()->create();
        $this->actingAs($this->actor())->put('/app/people/'.$p->id, [
            'firstname' => 'Grace', 'lastname' => 'Hopper',
        ])->assertRedirect();
        $this->assertSame('Grace Hopper', $p->refresh()->name);
    }

    public function test_destroy_blocked_when_owns_assets(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->delete('/app/people/'.$p->id)
            ->assertSessionHasErrors('person');
        $this->assertDatabaseHas('people', ['id' => $p->id]);
    }

    public function test_destroy_allowed_when_no_assets(): void
    {
        $p = Person::factory()->create();
        $this->actingAs($this->actor())->delete('/app/people/'.$p->id)->assertRedirect();
        $this->assertDatabaseMissing('people', ['id' => $p->id]);
    }

    public function test_show_lists_owned_assets(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->count(3)->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->get('/app/people/'.$p->id)
            ->assertInertia(fn (Assert $a) => $a->component('people/show')
                ->where('person.id', $p->id)->has('assets', 3)->etc());
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/people')->assertRedirect();
    }
}
