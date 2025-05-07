<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('state')->default(\App\Enums\AssetState::NEW->value);
            $table->foreignUuid('asset_type_id')->constrained('asset_types')->cascadeOnDelete();
            $table->foreignUuid('manufacturer_id')->constrained('manufacturers')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('model_id')->nullable()->constrained('asset_models')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->date('buy_date')->nullable();
            $table->string('buy_type')->nullable();
            $table->float('buy_price')->nullable();
            $table->date('guarantee_end')->nullable();
            $table->string('invoice')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
