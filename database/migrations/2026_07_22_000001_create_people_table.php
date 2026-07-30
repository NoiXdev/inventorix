<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Backfill one person per existing user, reusing the same UUID so the
        // ownership FKs (which currently hold user ids) re-point with no value
        // change. No-op on a fresh DB (tests) where users is empty.
        DB::table('users')->orderBy('id')->chunk(500, function ($users) {
            DB::table('people')->insert($users->map(fn ($u) => [
                'id' => $u->id,
                'firstname' => $u->firstname,
                'lastname' => $u->lastname,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
