<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetOwnerJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_user_assignee_options_are_sourced_from_users(): void
    {
        $user = User::factory()->withoutLogin()->create(['name' => 'Zelda Nomail']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get('/app/assets/create')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a
                ->component('assets/create')
                ->where('assigneeOptions.user', fn ($options) => collect($options)->pluck('label')->contains('Zelda Nomail'))
                ->etc());
    }

    public function test_create_page_user_assignee_options_tolerate_a_null_name(): void
    {
        // v2 allowed a user to exist with person_id = null, which post-merge
        // means name is NULL (name became nullable in 000001). The option
        // label must never be null — the frontend combobox calls
        // label.toLowerCase() on every option during initial render.
        $user = User::factory()->withoutLogin()->create(['name' => null, 'email' => null]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get('/app/assets/create')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a
                ->component('assets/create')
                ->where('assigneeOptions.user', function ($options) use ($user) {
                    $option = collect($options)->firstWhere('value', $user->id);

                    return $option !== null && $option['label'] !== null;
                })
                ->etc());
    }

    public function test_asset_index_is_searchable_by_an_account_less_owner_name(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => $owner->id]);
        Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => User::factory()->withoutLogin()->create()->id]);

        $this->actingAs(User::factory()->superuser()->create())
            ->get('/app/assets?search=Zelda')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a->component('assets/index')->has('assets.data', 1)->etc());
    }

    public function test_asset_export_includes_the_owner_name(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => $owner->id]);

        $response = $this->actingAs(User::factory()->superuser()->create())->get('/app/assets/export');

        $response->assertOk();
        $this->assertStringContainsString('Zelda Nomail', $response->streamedContent());
    }

    public function test_history_renders_the_name_of_an_account_less_owner(): void
    {
        $owner = User::factory()->withoutLogin()->create([
            'firstname' => 'Zelda', 'lastname' => 'Nomail', 'name' => 'Zelda Nomail', 'email' => null,
        ]);
        $asset = Asset::factory()->create(['assignee_type' => null, 'assignee_id' => null]);

        $this->actingAs(User::factory()->superuser()->create());
        $asset->assignTo($owner)->save();

        $this->get('/app/assets/'.$asset->id)
            ->assertOk()
            ->assertSee('Zelda Nomail', false);
    }

    public function test_index_shows_and_searches_location_and_asset_assignees(): void
    {
        $location = Location::factory()->create(['name' => 'Lager Nord']);
        $dock = Asset::factory()->create(['name' => 'Dock 7']);
        Asset::factory()->assignedTo($location)->create(['name' => 'In storage']);
        Asset::factory()->assignedTo($dock)->create(['name' => 'Monitor']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get('/app/assets?search=Lager')
            ->assertInertia(fn (Assert $a) => $a
                ->has('assets.data', 1)
                ->where('assets.data.0.assignee_name', 'Lager Nord')
                ->etc());

        $this->actingAs(User::factory()->superuser()->create())
            ->get('/app/assets?sort=assignee_name')
            ->assertInertia(fn (Assert $a) => $a
                ->where('assets.data', fn ($rows) => collect($rows)->firstWhere('name', 'Monitor')['assignee_name'] === 'Dock 7')
                ->etc());
    }
}
