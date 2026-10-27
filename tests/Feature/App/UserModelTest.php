<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_owns_assets(): void
    {
        $user = User::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $user->id]);

        $this->assertCount(2, $user->assets);
    }

    public function test_an_asset_owner_is_a_user(): void
    {
        $user = User::factory()->create(['firstname' => 'Ada', 'lastname' => 'Lovelace', 'name' => 'Ada Lovelace']);
        $asset = Asset::factory()->create(['owner_id' => $user->id]);

        $this->assertInstanceOf(User::class, $asset->owner);
        $this->assertSame('Ada Lovelace', $asset->owner->name);
    }

    public function test_without_login_state_creates_an_account_less_user(): void
    {
        $user = User::factory()->withoutLogin()->create();

        $this->assertFalse($user->login_enabled);
        $this->assertNull($user->password);
        $this->assertNotNull($user->name);
    }
}
