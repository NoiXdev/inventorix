<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->string('subject_type', 255)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->string('event')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->string('causer_id', 36)->nullable();
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'subject_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
