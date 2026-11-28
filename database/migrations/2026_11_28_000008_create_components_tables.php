<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Components (Snipe-IT's components and components_assets): parts such as
     * RAM or disks that are installed into assets and taken out again.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('model_number', 191)->nullable();
            $table->string('serial', 191)->nullable();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('order_number', 191)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 20, 2)->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('min_amt')->nullable();
            $table->string('image', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::create('component_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('component_id')->constrained('components')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('assigned_qty');
            $table->text('note')->nullable();
            $table->timestamp('checked_out_at');
            $table->timestamps();
            $table->creator();
            // Named: the generated name is longer than MariaDB's 64 characters.
            $table->unique(['component_id', 'asset_id'], 'component_assets_component_asset_unique');
        });

        // Existing read-only groups may see components too.
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            $permissions['components.view'] = 1;
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('component_assets');
        Schema::dropIfExists('components');
    }
};
