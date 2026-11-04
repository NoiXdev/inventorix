<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('months');
            $table->decimal('depreciation_min', 12, 2)->default(0);
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->decimal('rate_multiplier', 5, 2)->default(1);
            $table->string('depreciation_type')->default('amount');
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciations');
    }
};
