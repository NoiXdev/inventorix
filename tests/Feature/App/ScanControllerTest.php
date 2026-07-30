<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_existing_uuid_redirects_to_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->actor())->get('/app/scan/resolve?code='.$asset->id)
            ->assertRedirect(route('app.assets.show', $asset->id));
    }

    public function test_unknown_uuid_redirects_to_create_with_force_id(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->actor())->get('/app/scan/resolve?code='.$uuid)
            ->assertRedirect(route('app.assets.create', ['forceId' => $uuid]));
    }

    public function test_invalid_code_flashes_error(): void
    {
        $this->actingAs($this->actor())->from('/app')->get('/app/scan/resolve?code=not-a-uuid')
            ->assertRedirect('/app')->assertSessionHas('error');
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/scan/resolve?code=x')->assertRedirect();
    }
}
