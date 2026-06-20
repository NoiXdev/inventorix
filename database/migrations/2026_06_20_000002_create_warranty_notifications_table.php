<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('guarantee_end');
            $table->string('milestone');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->unique(['asset_id', 'guarantee_end', 'milestone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_notifications');
    }
};
