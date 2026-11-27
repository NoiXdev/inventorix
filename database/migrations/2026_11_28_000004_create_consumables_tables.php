<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consumables (Snipe-IT's consumables and consumables_users): items like
     * toner or cables that are handed to users and not taken back.
     */
    public function up(): void
    {
        Schema::create('consumables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('model_number', 191)->nullable();
            $table->string('item_no', 191)->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('min_amt')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 20, 2)->nullable();
            $table->string('order_number', 191)->nullable();
            $table->boolean('requestable')->default(false);
            $table->boolean('archived')->default(false);
            $table->string('image', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::create('consumable_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consumable_id')->constrained('consumables')->cascadeOnDelete();
            // The record stays when the user is deleted: what was used stays used.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 191)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->text('note')->nullable();
            $table->timestamp('checked_out_at');
            $table->timestamps();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_assignments');
        Schema::dropIfExists('consumables');
    }
};
