<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });

        Schema::dropIfExists('people');
    }

    public function down(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Re-create one person per user, same UUID, so the 000003 down() can
        // re-point the ownership FKs at people without rewriting any value.
        DB::table('users')->orderBy('id')->chunkById(500, function ($users) {
            DB::table('people')->insert($users->map(fn ($u) => [
                'id' => $u->id,
                'firstname' => (string) $u->firstname,
                'lastname' => (string) $u->lastname,
                'name' => (string) $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ])->all());
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('person_id')->nullable()->after('id')->constrained('people')->nullOnDelete();
        });

        DB::table('users')->update(['person_id' => DB::raw('id')]);
    }
};
