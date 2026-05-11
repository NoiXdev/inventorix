<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('handover_asset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('handover_id')->constrained('handovers')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('state_from');
            $table->string('state_to');
            $table->foreignUuid('owner_from_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('owner_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['handover_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_asset');
    }
};
