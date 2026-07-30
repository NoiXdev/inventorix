<?php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetForcedIdTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'state' => AssetState::NEW->value,
            'asset_type_id' => AssetType::factory()->create()->id,
        ], $overrides);
    }

    public function test_store_uses_forced_uuid(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['id' => $uuid]))
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $uuid]);
    }

    public function test_store_rejects_duplicate_id(): void
    {
        $existing = Asset::factory()->create();

        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['id' => $existing->id]))
            ->assertSessionHasErrors('id');
    }

    public function test_store_without_id_autogenerates(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', $this->payload())->assertRedirect();
        $this->assertSame(1, Asset::query()->count());
    }

    public function test_update_ignores_a_submitted_id(): void
    {
        $asset = Asset::factory()->create();
        $originalId = $asset->id;

        // The shared AssetForm carries an `id` field; an edit must never change the key.
        $this->actingAs($this->actor())->put('/app/assets/'.$asset->id, $this->payload([
            'id' => (string) Str::uuid(),
            'serial_number' => 'EDITED',
        ]))->assertRedirect();

        $asset->refresh();
        $this->assertSame($originalId, $asset->id);
        $this->assertSame('EDITED', $asset->serial_number);
    }
}
