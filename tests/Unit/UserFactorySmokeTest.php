<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_user_satisfying_schema(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->firstname);
        $this->assertNotEmpty($user->lastname);
        $this->assertIsBool($user->login_enabled);
    }
}
