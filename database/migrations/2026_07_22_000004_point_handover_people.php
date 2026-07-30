<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_user_id']);
            $table->renameColumn('recipient_user_id', 'recipient_person_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_person_id')->references('id')->on('people')->nullOnDelete();
        });
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('people')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_person_id']);
            $table->renameColumn('recipient_person_id', 'recipient_user_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
