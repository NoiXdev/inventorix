<?php

namespace Tests\Feature\App;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Asset-ownership coverage (counts, destroy-guard, owned-assets listing) moved to
// User in this task (Person::assets() still exists but owner_id no longer holds
// Person ids). It is reinstated against UserController in Task 5; PersonController
// itself is removed in Task 7.
class PersonControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
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

    public function test_destroy_allowed_when_no_assets(): void
    {
        $p = Person::factory()->create();
        $this->actingAs($this->actor())->delete('/app/people/'.$p->id)->assertRedirect();
        $this->assertDatabaseMissing('people', ['id' => $p->id]);
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/people')->assertRedirect();
    }
}
