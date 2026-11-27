<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Software licenses with seats (Snipe-IT's licenses and license_seats).
     * A seat is checked out to a user or an asset; a seat of a license that
     * is not reassignable is retired when it comes back.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191);
            // Encrypted by the model cast, hence text.
            $table->text('product_key')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('depreciation_id')->nullable()->constrained('depreciations')->nullOnDelete();
            $table->unsignedInteger('seats')->default(1);
            $table->string('licensed_to_name', 191)->nullable();
            $table->string('licensed_to_email', 191)->nullable();
            $table->boolean('reassignable')->default(true);
            $table->boolean('maintained')->default(false);
            $table->boolean('requestable')->default(false);
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 20, 2)->nullable();
            $table->string('order_number', 191)->nullable();
            $table->string('purchase_order', 191)->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->unsignedInteger('min_amt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        Schema::create('license_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            // A returned seat of a license that is not reassignable is used up.
            $table->boolean('retired')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            // Named: the generated name is longer than MariaDB's 64 characters.
            $table->index(['license_id', 'assigned_user_id', 'assigned_asset_id'], 'license_seats_holder_index');
        });

        // Existing read-only groups may see licenses too.
        foreach (DB::table('groups')->where('name', 'Read only')->get() as $group) {
            $permissions = json_decode($group->permissions ?? '{}', true) ?: [];
            $permissions['licenses.view'] = 1;
            DB::table('groups')->where('id', $group->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_seats');
        Schema::dropIfExists('licenses');
    }
};
