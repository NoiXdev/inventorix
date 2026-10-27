<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MergePeopleIntoUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePreMergeSchema();
    }

    /**
     * RefreshDatabase runs every migration, so by Task 7 neither `people` nor
     * `users.person_id` survives the setup — and on an empty database the merge
     * itself was a no-op. Rebuild the v2 shape these tests exist to exercise.
     * The conditionals make this correct both before and after Task 7.
     */
    private function ensurePreMergeSchema(): void
    {
        if (! Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('firstname');
                $table->string('lastname');
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'person_id')) {
            // No FK needed: the merge migration only reads this column.
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('person_id')->nullable();
            });
        }

        DB::table('people')->delete();
        DB::table('users')->delete();
    }

    /** Re-run just the merge step against rows seeded in the pre-merge shape. */
    private function runMerge(): void
    {
        (require base_path('database/migrations/2026_10_27_000002_merge_people_into_users.php'))->up();
    }

    private function seedPerson(array $attrs = []): string
    {
        $id = (string) Str::uuid();
        DB::table('people')->insert(array_merge([
            'id' => $id,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'name' => 'Ada Lovelace',
            'email' => 'ada-'.$id.'@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return $id;
    }

    private function seedUser(array $attrs = []): string
    {
        $id = (string) Str::uuid();
        DB::table('users')->insert(array_merge([
            'id' => $id,
            'person_id' => null,
            'email' => 'user-'.$id.'@example.test',
            'password' => 'x',
            'login_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return $id;
    }

    public function test_account_less_person_becomes_a_user_with_the_same_uuid(): void
    {
        $personId = $this->seedPerson(['firstname' => 'Grace', 'lastname' => 'Hopper', 'name' => 'Grace Hopper']);

        $this->runMerge();

        $this->assertDatabaseHas('users', [
            'id' => $personId,
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
            'name' => 'Grace Hopper',
            'login_enabled' => 0,
        ]);
        $this->assertNull(DB::table('users')->where('id', $personId)->value('password'));
    }

    public function test_linked_user_absorbs_the_person_name_and_keeps_its_own_login_email(): void
    {
        $personId = $this->seedPerson(['email' => 'contact@example.test', 'firstname' => 'Alan', 'lastname' => 'Turing', 'name' => 'Alan Turing']);
        $userId = $this->seedUser(['email' => 'login@example.test', 'person_id' => $personId]);

        $this->runMerge();

        $row = DB::table('users')->where('id', $userId)->first();
        $this->assertSame('Alan', $row->firstname);
        $this->assertSame('Turing', $row->lastname);
        $this->assertSame('Alan Turing', $row->name);
        $this->assertSame('login@example.test', $row->email, 'the login address must win over the person contact address');
    }

    public function test_linked_user_without_an_email_adopts_the_person_email(): void
    {
        $personId = $this->seedPerson(['email' => 'contact@example.test']);
        $userId = $this->seedUser(['email' => null, 'person_id' => $personId]);

        $this->runMerge();

        $this->assertSame('contact@example.test', DB::table('users')->where('id', $userId)->value('email'));
    }

    public function test_no_duplicate_user_is_created_for_a_linked_person(): void
    {
        $personId = $this->seedPerson();
        $this->seedUser(['person_id' => $personId]);

        $this->runMerge();

        $this->assertSame(1, DB::table('users')->where('person_id', $personId)->count());
        $this->assertSame(0, DB::table('users')->where('id', $personId)->count());
    }

    public function test_person_email_colliding_with_an_existing_user_is_nulled_and_logged(): void
    {
        $this->seedUser(['email' => 'shared@example.test']);
        $personId = $this->seedPerson(['email' => 'shared@example.test']);

        Log::shouldReceive('warning')->once()
            ->with('merge-people-into-users: dropped colliding email', \Mockery::on(
                fn (array $ctx) => $ctx['person_id'] === $personId && $ctx['email'] === 'shared@example.test'
            ));

        $this->runMerge();

        $this->assertNull(DB::table('users')->where('id', $personId)->value('email'));
        $this->assertSame(1, DB::table('users')->where('email', 'shared@example.test')->count());
    }

    public function test_person_without_an_email_becomes_a_user_with_a_null_email(): void
    {
        $personId = $this->seedPerson(['email' => null]);

        $this->runMerge();

        $this->assertDatabaseHas('users', ['id' => $personId, 'login_enabled' => 0]);
        $this->assertNull(DB::table('users')->where('id', $personId)->value('email'));
    }

    public function test_two_people_without_emails_do_not_collide(): void
    {
        $a = $this->seedPerson(['email' => null]);
        $b = $this->seedPerson(['email' => null]);

        $this->runMerge();

        $this->assertDatabaseHas('users', ['id' => $a]);
        $this->assertDatabaseHas('users', ['id' => $b]);
    }

    public function test_person_whose_id_already_belongs_to_an_unrelated_user_is_skipped_not_duplicated(): void
    {
        $sharedId = (string) Str::uuid();
        $otherPersonId = $this->seedPerson(['firstname' => 'Other', 'lastname' => 'Link', 'name' => 'Other Link']);

        // Person A shares its UUID with user U — the original v2 backfill
        // invariant — but U has since been re-pointed at a different person
        // via v2's user-edit form. A is now, from this migration's point of
        // view, "account-less", yet its id collides with an existing user.
        DB::table('people')->insert([
            'id' => $sharedId,
            'firstname' => 'Stale',
            'lastname' => 'Identity',
            'name' => 'Stale Identity',
            'email' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id' => $sharedId,
            'person_id' => $otherPersonId,
            'email' => 'login@example.test',
            'password' => 'x',
            'login_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::shouldReceive('warning')->once()
            ->with('merge-people-into-users: skipped person with id already used by an unrelated user', \Mockery::on(
                fn (array $ctx) => $ctx['person_id'] === $sharedId
            ));

        $this->runMerge();

        // No duplicate insert was attempted; the existing user row survives,
        // having absorbed the *linked* person's identity as usual.
        $this->assertSame(1, DB::table('users')->where('id', $sharedId)->count());
        $row = DB::table('users')->where('id', $sharedId)->first();
        $this->assertSame('Other Link', $row->name);
    }

    public function test_two_account_less_people_sharing_an_email_do_not_collide(): void
    {
        $a = $this->seedPerson(['email' => 'dup@example.test']);
        $b = $this->seedPerson(['email' => 'dup@example.test']);

        // The merge processes people ordered by id (chunkById), not insertion
        // order, so whichever UUID sorts first is the one that keeps the email.
        [$winner, $loser] = $a < $b ? [$a, $b] : [$b, $a];

        Log::shouldReceive('warning')->once()
            ->with('merge-people-into-users: dropped colliding email', \Mockery::on(
                fn (array $ctx) => $ctx['person_id'] === $loser && $ctx['email'] === 'dup@example.test'
            ));

        $this->runMerge();

        $this->assertSame('dup@example.test', DB::table('users')->where('id', $winner)->value('email'));
        $this->assertNull(DB::table('users')->where('id', $loser)->value('email'));
    }
}
