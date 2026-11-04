<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            $table->longText('eula_text')->nullable();
            $table->boolean('use_default_eula')->default(false);
            $table->boolean('require_acceptance')->default(false);
            $table->boolean('alert_on_response')->default(false);
            $table->string('category_type', 191)->nullable()->default('asset');
            $table->boolean('checkin_email')->default(false);
            $table->string('image', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
