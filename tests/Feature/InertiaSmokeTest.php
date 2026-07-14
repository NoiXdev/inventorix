<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_inertia_smoke_page_at_app(): void
    {
        // Task 10 guards the whole /app group with `auth`, so the smoke test
        // now needs an authenticated user to reach the dashboard.
        $user = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($user)->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }
}
