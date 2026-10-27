<?php

use App\Support\Migrations\IdentityReferenceGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only the handover recipient moves here. The asset-ownership FKs move in
        // Task 3, together with the AssetFactory flip to User — re-pointing them
        // before that flip breaks every test that builds an Asset, because a
        // freshly created Person has no matching users row.
        IdentityReferenceGuard::assertAllResolve([
            ['handovers', 'recipient_person_id'],
        ]);

        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_person_id']);
            $table->renameColumn('recipient_person_id', 'recipient_user_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_user_id']);
            $table->renameColumn('recipient_user_id', 'recipient_person_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_person_id')->references('id')->on('people')->nullOnDelete();
        });
    }
};
