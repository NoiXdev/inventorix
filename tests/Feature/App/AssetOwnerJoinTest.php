<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetOwnerJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_owner_options_are_sourced_from_users(): void
    {
        $user = User::factory()->withoutLogin()->create(['name' => 'Zelda Nomail']);

        $this->actingAs(User::factory()->create())
            ->get('/app/assets/create')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a
                ->component('assets/create')
                ->where('ownerOptions', fn ($options) => collect($options)->pluck('label')->contains('Zelda Nomail'))
                ->etc());
    }

    public function test_asset_index_is_searchable_by_an_account_less_owner_name(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        Asset::factory()->create(['owner_id' => $owner->id]);
        Asset::factory()->create(['owner_id' => User::factory()->withoutLogin()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->get('/app/assets?search=Zelda')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a->component('assets/index')->has('assets.data', 1)->etc());
    }

    public function test_asset_export_includes_the_owner_name(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        Asset::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs(User::factory()->create())->get('/app/assets/export');

        $response->assertOk();
        $this->assertStringContainsString('Zelda Nomail', $response->streamedContent());
    }

    public function test_history_renders_the_name_of_an_account_less_owner(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        $asset = Asset::factory()->create(['owner_id' => null]);

        $this->actingAs(User::factory()->create());
        $asset->update(['owner_id' => $owner->id]);

        $this->get('/app/assets/'.$asset->id)
            ->assertOk()
            ->assertSee('Zelda Nomail', false);
    }
}
