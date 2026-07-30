<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_links_to_a_person(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);
        $this->assertTrue($user->person->is($person));
    }

    public function test_person_can_be_created_with_no_assets(): void
    {
        $person = Person::factory()->create();
        $this->assertCount(0, $person->assets);
    }

    public function test_person_has_assets(): void
    {
        $person = Person::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $person->id]);

        $this->assertCount(2, $person->assets);
    }
}
