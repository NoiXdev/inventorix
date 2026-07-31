<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonQuickStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_quick_create_a_person(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/app/people/quick', [
            'firstname' => 'Max',
            'lastname' => 'Muster',
            'email' => 'max@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name'])
            ->assertJsonPath('name', 'Max Muster');

        $this->assertDatabaseHas('people', [
            'firstname' => 'Max',
            'lastname' => 'Muster',
            'name' => 'Max Muster',
            'email' => 'max@example.com',
        ]);
    }

    public function test_it_can_quick_create_a_person_without_an_email(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/app/people/quick', [
            'firstname' => 'Max',
            'lastname' => 'Muster',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Max Muster');

        $this->assertDatabaseHas('people', [
            'name' => 'Max Muster',
            'email' => null,
        ]);
    }

    public function test_lastname_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/app/people/quick', ['firstname' => 'Max'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lastname');
    }

    public function test_guests_cannot_quick_create(): void
    {
        $this->postJson('/app/people/quick', [
            'firstname' => 'Max',
            'lastname' => 'Muster',
        ])->assertUnauthorized();
    }
}
