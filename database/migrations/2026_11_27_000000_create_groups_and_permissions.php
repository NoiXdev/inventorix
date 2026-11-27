<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->json('permissions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable();
        });

        // Today every user who can sign in may do everything; keep it that way.
        DB::table('users')->where('login_enabled', true)
            ->update(['permissions' => json_encode(['superuser' => 1])]);

        // Raw values on purpose: this stays valid if the enum or models change.
        $viewGrants = ['reports.view' => 1];
        foreach (['assets', 'incidents', 'handovers', 'accessories', 'models', 'categories', 'manufacturers', 'suppliers', 'locations', 'statuslabels', 'customfields', 'depreciations', 'users'] as $section) {
            $viewGrants[$section.'.view'] = 1;
        }

        $now = now();
        DB::table('groups')->insert([
            ['id' => (string) Str::uuid(), 'name' => 'IT-Admin', 'permissions' => json_encode(['admin' => 1]), 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::uuid(), 'name' => 'Read only', 'permissions' => json_encode($viewGrants), 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
