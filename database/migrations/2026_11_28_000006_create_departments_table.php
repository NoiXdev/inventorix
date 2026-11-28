<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Departments (Snipe-IT's departments): a user belongs to at most one. */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });

        // Existing read-only groups may see departments too.
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            $permissions['departments.view'] = 1;
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('departments');
    }
};
