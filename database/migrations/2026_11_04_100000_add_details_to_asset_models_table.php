<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_models', function (Blueprint $table) {
            $table->string('model_number', 191)->nullable();
            $table->unsignedInteger('min_amt')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('require_serial')->default(false);
            $table->foreignUuid('depreciation_id')->nullable()->constrained('depreciations')->nullOnDelete();
            // End of life, in months after purchase.
            $table->unsignedInteger('eol')->nullable();
            $table->string('image', 191)->nullable();
            $table->foreignUuid('fieldset_id')->nullable()->constrained('custom_fieldsets')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('requestable')->default(false);
            $table->softDeletes();
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::table('asset_models', function (Blueprint $table) {
            $table->dropCreator();
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('depreciation_id');
            $table->dropConstrainedForeignId('fieldset_id');
            $table->dropColumn(['model_number', 'min_amt', 'require_serial', 'eol', 'image', 'notes', 'requestable']);
        });
    }
};
