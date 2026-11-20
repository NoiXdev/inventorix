<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191)->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->boolean('requestable')->default(false);
            $table->boolean('archived')->default(false);
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('default_purchase_cost', 20, 2)->nullable();
            $table->unsignedInteger('min_amt')->nullable();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('model_number', 191)->nullable();
            $table->string('image', 191)->nullable();
            $table->foreignUuid('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessories');
    }
};
