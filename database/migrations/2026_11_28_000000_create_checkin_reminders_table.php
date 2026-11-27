<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which expected check-in an assignee was already reminded of, so the
     * daily run reminds once per asset and date instead of every day.
     */
    public function up(): void
    {
        Schema::create('checkin_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->date('expected_checkin');
            $table->timestamp('sent_at');
            $table->unique(['asset_id', 'expected_checkin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_reminders');
    }
};
