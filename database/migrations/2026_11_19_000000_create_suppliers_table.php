<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            $table->string('address', 250)->nullable();
            $table->string('address2', 250)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('state', 191)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('phone', 35)->nullable();
            $table->string('fax', 35)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contact', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('url', 250)->nullable();
            $table->string('image', 191)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
