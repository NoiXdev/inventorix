<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable on purpose: the merge in 000002 must not fail on a login
            // row that has no person attached (the admin account on a fresh v2
            // install is exactly that). The form layer requires them instead.
            $table->string('firstname')->nullable()->after('id');
            $table->string('lastname')->nullable()->after('firstname');
            $table->string('name')->nullable()->after('lastname');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname', 'name']);
        });
    }
};
