<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FilamentRelocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_the_filament_login_at_the_new_app_old_path(): void
    {
        $this->get('/app-old/login')->assertOk();
    }

    public function test_no_longer_serves_filament_at_the_old_app_path(): void
    {
        // /app is reserved for the new Inertia app (Task 10 claimed /app/login for
        // its own auth pages); Filament's login must not answer here.
        $this->get('/app/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_keeps_the_filament_app_route_names_resolving(): void
    {
        $this->assertStringContainsString('/app-old/login', route('filament.app.auth.login'));
    }
}
