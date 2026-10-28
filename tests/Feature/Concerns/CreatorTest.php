<?php

namespace Tests\Feature\Concerns;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stamps_the_authenticated_user_on_create(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $manufacturer = Manufacturer::create(['name' => 'Acme']);

        $this->assertSame($user->id, $manufacturer->created_by);
        $this->assertSame($user->id, $manufacturer->updated_by);
    }

    public function test_it_leaves_the_stamps_null_without_an_authenticated_user(): void
    {
        // Seeders, queued jobs and console commands run with no auth context;
        // that must yield NULL rather than blowing up.
        $manufacturer = Manufacturer::create(['name' => 'Acme']);

        $this->assertNull($manufacturer->created_by);
        $this->assertNull($manufacturer->updated_by);
    }

    public function test_it_stamps_the_updater_on_update_without_touching_the_creator(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($author);
        $manufacturer = Manufacturer::create(['name' => 'Acme']);

        $this->actingAs($editor);
        $manufacturer->update(['name' => 'Acme Corp']);

        $this->assertSame($author->id, $manufacturer->fresh()->created_by, 'created_by must survive an update');
        $this->assertSame($editor->id, $manufacturer->fresh()->updated_by);
    }

    public function test_it_does_not_overwrite_explicitly_set_stamps(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($actor);

        $manufacturer = new Manufacturer(['name' => 'Acme']);
        $manufacturer->created_by = $other->id;
        $manufacturer->save();

        $this->assertSame($other->id, $manufacturer->created_by);
    }

    public function test_it_persists_deleted_by_on_a_soft_delete(): void
    {
        // Eloquent's runSoftDelete() writes only deleted_at/updated_at, so an
        // attribute merely assigned in the `deleting` hook never reaches the
        // database. This is the regression guard for that.
        $user = User::factory()->create();
        $this->actingAs($user);

        $manufacturer = Manufacturer::create(['name' => 'Acme']);
        $manufacturer->delete();

        $row = DB::table('manufacturers')->where('id', $manufacturer->id)->first();

        $this->assertNotNull($row, 'the row must survive as a soft delete');
        $this->assertNotNull($row->deleted_at);
        $this->assertSame($user->id, $row->deleted_by);
    }

    public function test_a_soft_deleted_row_disappears_from_normal_queries(): void
    {
        $this->actingAs(User::factory()->create());

        $manufacturer = Manufacturer::create(['name' => 'Acme']);
        $manufacturer->delete();

        $this->assertNull(Manufacturer::find($manufacturer->id));
        $this->assertNotNull(Manufacturer::withTrashed()->find($manufacturer->id));
    }

    public function test_force_delete_removes_the_row_entirely(): void
    {
        $this->actingAs(User::factory()->create());

        $manufacturer = Manufacturer::create(['name' => 'Acme']);
        $manufacturer->forceDelete();

        $this->assertNull(DB::table('manufacturers')->where('id', $manufacturer->id)->first());
    }

    public function test_the_relations_resolve_to_the_stamping_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $manufacturer = Manufacturer::create(['name' => 'Acme']);
        $manufacturer->delete();

        $manufacturer = Manufacturer::withTrashed()->findOrFail($manufacturer->id);

        $this->assertTrue($manufacturer->creator->is($user));
        $this->assertTrue($manufacturer->updater->is($user));
        $this->assertTrue($manufacturer->deleter->is($user));
    }
}
