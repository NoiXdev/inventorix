<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserFactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_login_only_user_satisfying_schema(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->email);
        $this->assertIsBool($user->login_enabled);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->person_id);
    }
}
