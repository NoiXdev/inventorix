<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accessories checked out to a user, a location or an asset (Snipe-IT's
     * accessories_checkout): one row per unit, removed when it comes back.
     */
    public function up(): void
    {
        Schema::create('accessory_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('accessory_id')->constrained('accessories')->cascadeOnDelete();
            $table->string('assigned_type', 191);
            $table->uuid('assigned_id');
            $table->text('note')->nullable();
            $table->timestamp('checked_out_at');
            $table->timestamps();
            $table->creator();
            $table->index(['assigned_type', 'assigned_id'], 'accessory_checkouts_assigned_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_checkouts');
    }
};
