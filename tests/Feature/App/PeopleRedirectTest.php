<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_index_redirects_permanently_to_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/people')
            ->assertRedirect('/app/users')
            ->assertStatus(301);
    }

    public function test_people_detail_redirects_permanently_to_the_user(): void
    {
        $u = User::factory()->withoutLogin()->create();

        $this->actingAs(User::factory()->create())
            ->get('/app/people/'.$u->id)
            ->assertRedirect('/app/users/'.$u->id)
            ->assertStatus(301);
    }
}
