<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('format')->nullable();
            $table->string('element');
            $table->text('field_values')->nullable();
            $table->boolean('field_encrypted')->default(false);
            $table->string('db_column')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('show_in_email')->default(false);
            $table->boolean('show_in_requestable_list')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('display_in_user_view')->default(false);
            $table->boolean('auto_add_to_fieldsets')->default(false);
            $table->boolean('show_in_listview')->default(false);
            $table->boolean('display_checkin')->default(false);
            $table->boolean('display_audit')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
