<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fieldsets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::create('custom_field_has_custom_fieldsets', function (Blueprint $table) {
            $table->foreignUuid('custom_fieldset_id')->references('id')->on('custom_fieldsets')->cascadeOnDelete();
            $table->foreignUuid('custom_field_id')->references('id')->on('custom_fields')->cascadeOnDelete();
            // Per fieldset: where the field appears and whether it must be filled in.
            $table->unsignedInteger('order')->default(0);
            $table->boolean('required')->default(false);

            // Named explicitly: the generated name is 77 characters, past MariaDB's 64.
            $table->unique(['custom_fieldset_id', 'custom_field_id'], 'custom_fieldset_field_unique');
        });
    }

    public function down(): void
    {
        // The pivot references custom_fieldsets, so it has to go first.
        Schema::dropIfExists('custom_field_has_custom_fieldsets');
        Schema::dropIfExists('custom_fieldsets');
    }
};
