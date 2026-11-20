<?php

namespace Tests\Feature\App;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
        Storage::fake();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Berlin HQ',
            'parent_id' => '',
            'manager_id' => $this->user->id,
            'address' => 'Unter den Linden 1',
            'address2' => '',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'zip' => '10117',
            'country' => 'DE',
            'phone' => '+49 30 123456',
            'fax' => '',
            'currency' => 'EUR',
            'ldap_ou' => 'OU=Berlin,DC=example,DC=com',
            'notes' => 'Head office.',
            'remove_image' => '0',
            ...$overrides,
        ];
    }

    public function test_index_lists_locations_with_parent_path_manager_and_asset_count(): void
    {
        $hq = Location::factory()->create(['name' => 'HQ']);
        $floor = Location::factory()->create(['name' => 'Floor 2', 'parent_id' => $hq->id, 'manager_id' => $this->user->id]);
        $room = Location::factory()->create(['name' => 'Room 201', 'parent_id' => $floor->id, 'country' => 'DE']);
        Asset::factory()->count(2)->create(['location_id' => $room->id]);

        $this->actingAs($this->user)->get('/app/locations?sort=name&filter[parent_id]='.$floor->id)
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('locations/index')
                ->has('locations.data', 1)
                ->where('locations.data.0.name', 'Room 201')
                ->where('locations.data.0.parent_path', 'HQ › Floor 2')
                ->where('locations.data.0.assets_count', 2)
                ->has('countryOptions', 1)
                ->where('parentOptions.2.label', 'HQ › Floor 2 › Room 201'));

        $this->actingAs($this->user)->get('/app/locations?search=Floor')
            ->assertInertia(fn (Assert $p) => $p->has('locations.data', 1)
                ->where('locations.data.0.manager_name', $this->user->name));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/locations')->assertRedirect();
    }

    public function test_places_redirects_to_locations(): void
    {
        $this->actingAs($this->user)->get('/app/places')->assertRedirect('/app/locations');
    }

    public function test_store_creates_a_location_and_stamps_the_creator(): void
    {
        $parent = Location::factory()->create();

        $this->actingAs($this->user)->post('/app/locations', $this->payload(['parent_id' => $parent->id]))
            ->assertRedirect('/app/locations')
            ->assertSessionHasNoErrors();

        $l = Location::where('name', 'Berlin HQ')->sole();
        $this->assertSame($parent->id, $l->parent_id);
        $this->assertSame($this->user->id, $l->manager_id);
        $this->assertSame('DE', $l->country);
        $this->assertSame('EUR', $l->currency);
        $this->assertSame('OU=Berlin,DC=example,DC=com', $l->ldap_ou);
        $this->assertNull($l->fax);
        $this->assertSame($this->user->id, $l->created_by);
    }

    public function test_store_validates_input(): void
    {
        $trashed = Location::factory()->create();
        $trashed->delete();

        $this->actingAs($this->user)->post('/app/locations', $this->payload([
            'name' => '',
            'parent_id' => $trashed->id,
            'manager_id' => '00000000-0000-0000-0000-000000000000',
            'country' => 'XX',
            'zip' => str_repeat('1', 11),
            'phone' => str_repeat('1', 21),
            'currency' => str_repeat('E', 11),
        ]))->assertSessionHasErrors(['name', 'parent_id', 'manager_id', 'country', 'zip', 'phone', 'currency']);
    }

    public function test_a_location_cannot_be_moved_below_itself(): void
    {
        $hq = Location::factory()->create(['name' => 'HQ']);
        $floor = Location::factory()->create(['parent_id' => $hq->id]);
        $room = Location::factory()->create(['parent_id' => $floor->id]);

        foreach ([$hq->id, $floor->id, $room->id] as $parent) {
            $this->actingAs($this->user)->put("/app/locations/{$hq->id}", $this->payload(['name' => 'HQ', 'parent_id' => $parent]))
                ->assertSessionHasErrors('parent_id');
        }

        // ...and the edit form doesn't offer them in the first place.
        $this->actingAs($this->user)->get("/app/locations/{$floor->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('parentOptions', [['value' => $hq->id, 'label' => 'HQ']]));
    }

    public function test_image_must_not_be_svg_and_is_not_mass_assignable(): void
    {
        $this->actingAs($this->user)->post('/app/locations', $this->payload([
            'image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($this->user)->post('/app/locations', $this->payload(['image' => 'secret/file.pdf']))
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Location::count());
    }

    public function test_image_is_stored_served_replaced_and_removed(): void
    {
        $this->actingAs($this->user)->post('/app/locations', $this->payload([
            'image' => UploadedFile::fake()->image('a.png'),
        ]))->assertSessionHasNoErrors();

        $l = Location::sole();
        $first = $l->image;
        Storage::assertExists($first);
        $this->actingAs($this->user)->get("/app/locations/{$l->id}/image")->assertOk();

        $this->actingAs($this->user)->put("/app/locations/{$l->id}", $this->payload(['remove_image' => '1']))
            ->assertRedirect('/app/locations');
        Storage::assertMissing($first);
        $this->assertNull($l->fresh()->image);
    }

    public function test_edit_renders_the_location(): void
    {
        $l = Location::factory()->create(['name' => 'Lager', 'currency' => 'CHF']);
        $this->actingAs($this->user)->get("/app/locations/{$l->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('locations/edit')
                ->where('location.name', 'Lager')
                ->where('location.currency', 'CHF')
                ->where('location.image_url', null)
                ->has('managerOptions')
                ->has('countryOptions', 249));
    }

    public function test_update_changes_a_location(): void
    {
        $l = Location::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user)->put("/app/locations/{$l->id}", $this->payload(['name' => 'New', 'manager_id' => '']))
            ->assertRedirect('/app/locations');
        $fresh = $l->fresh();
        $this->assertSame('New', $fresh->name);
        $this->assertNull($fresh->manager_id);
        $this->assertSame($this->user->id, $fresh->updated_by);
    }

    public function test_destroy_soft_deletes_and_assets_still_show_the_location(): void
    {
        $l = Location::factory()->create(['name' => 'Gone']);
        $asset = Asset::factory()->create(['location_id' => $l->id]);

        $this->actingAs($this->user)->delete("/app/locations/{$l->id}")
            ->assertRedirect('/app/locations');

        $this->assertSoftDeleted($l);
        $this->assertSame($this->user->id, Location::withTrashed()->find($l->id)->deleted_by);
        $this->actingAs($this->user)->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('asset.location_name', 'Gone'));
    }

    public function test_assets_cannot_be_assigned_a_trashed_location(): void
    {
        $l = Location::factory()->create();
        $l->delete();
        $asset = Asset::factory()->create();

        $this->actingAs($this->user)->put("/app/assets/{$asset->id}", [
            'status_id' => StatusLabel::system(SystemStatus::NEW)->id, 'location_id' => $l->id,
        ])->assertSessionHasErrors('location_id');
    }
}
