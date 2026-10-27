<?php

use App\Support\Migrations\IdentityReferenceGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdentityReferenceGuard::assertAllResolve([
            ['assets', 'owner_id'],
            ['handover_asset', 'owner_from_id'],
            ['handover_asset', 'owner_to_id'],
        ]);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('people')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('people')->nullOnDelete();
        });
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('people')->nullOnDelete();
        });
    }
};
