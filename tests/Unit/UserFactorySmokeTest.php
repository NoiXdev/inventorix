<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserFactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_full_identity_satisfying_schema(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->email);
        $this->assertIsBool($user->login_enabled);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_the_factory_produces_a_display_name(): void
    {
        $user = User::factory()->make();

        $this->assertNotEmpty($user->firstname);
        $this->assertNotEmpty($user->lastname);
        $this->assertSame($user->firstname.' '.$user->lastname, $user->name);
    }
}
