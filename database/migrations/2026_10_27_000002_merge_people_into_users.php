<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Linked users absorb their person's identity. The login address wins;
        //    the person's contact address is only adopted when the user has none.
        DB::table('users')->whereNotNull('person_id')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $person = DB::table('people')->where('id', $user->person_id)->first();
                if (! $person) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'firstname' => $person->firstname,
                    'lastname' => $person->lastname,
                    'name' => $person->name,
                    'email' => $user->email ?: $this->freeEmail($person->email, $person->id),
                ]);
            }
        });

        // 2. Every person with no account becomes a login-disabled user, REUSING
        //    the person's UUID. That is what lets 000003 re-point the ownership
        //    FKs without rewriting a single value.
        $linked = DB::table('users')->whereNotNull('person_id')->pluck('person_id')->all();

        // Inserted one row at a time (not a batch insert) so freeEmail()'s
        // collision check sees rows already written earlier in this same
        // chunk, not just previously committed ones. Two account-less people
        // sharing a non-null email would otherwise both pass the check before
        // either was inserted, and the batch insert would throw a unique
        // constraint violation on the second row.
        DB::table('people')->whereNotIn('id', $linked ?: ['-'])->orderBy('id')->chunkById(500, function ($people) {
            foreach ($people as $p) {
                DB::table('users')->insert([
                    'id' => $p->id,
                    'person_id' => null,
                    'firstname' => $p->firstname,
                    'lastname' => $p->lastname,
                    'name' => $p->name,
                    'email' => $this->freeEmail($p->email, $p->id),
                    'password' => null,
                    'login_enabled' => 0,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ]);
            }
        });
    }

    /**
     * users.email is UNIQUE and people.email never was. Drop a colliding address
     * rather than fail the migration, and leave a trail so it can be reconciled.
     */
    private function freeEmail(?string $email, string $personId): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        if (DB::table('users')->where('email', $email)->exists()) {
            Log::warning('merge-people-into-users: dropped colliding email', [
                'person_id' => $personId,
                'email' => $email,
            ]);

            return null;
        }

        return $email;
    }

    public function down(): void
    {
        // Remove only the rows this migration synthesised; linked users keep
        // their (now redundant) name columns, which 000001's down() drops.
        DB::table('users')
            ->whereNull('person_id')
            ->whereIn('id', fn ($q) => $q->select('id')->from('people'))
            ->delete();
    }
};
