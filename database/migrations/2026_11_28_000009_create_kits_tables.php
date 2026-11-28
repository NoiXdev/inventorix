<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Predefined kits (Snipe-IT's kits): a named bundle of asset models,
     * licenses, accessories and consumables that is checked out in one go.
     */
    public function up(): void
    {
        Schema::create('kits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::create('kit_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kit_id')->constrained('kits')->cascadeOnDelete();
            // Named: the generated index name is longer than MariaDB's 64 characters.
            $table->uuidMorphs('item', 'kit_items_item_index');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
            $table->unique(['kit_id', 'item_type', 'item_id'], 'kit_items_unique');
        });

        // Existing read-only groups may see kits too.
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            $permissions['kits.view'] = 1;
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_items');
        Schema::dropIfExists('kits');
    }
};
