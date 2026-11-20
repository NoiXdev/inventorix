<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings assets in line with the new asset fields:
 * - serial_number, buy_date and buy_price are renamed to serial,
 *   purchase_date and purchase_cost;
 * - the owner becomes a polymorphic assignee (user, location or asset), and
 *   existing owners are carried over as user assignees;
 * - buy_type, guarantee_end and invoice are dropped (the warranty is now
 *   purchase_date + warranty_months);
 * - the new fields, the creator columns and soft deletes are added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('serial_number', 'serial');
            $table->renameColumn('buy_date', 'purchase_date');
            $table->renameColumn('buy_price', 'purchase_cost');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('purchase_cost', 20, 2)->nullable()->change();

            $table->string('name', 191)->nullable()->after('id');
            $table->string('asset_tag', 191)->nullable()->after('name');
            $table->nullableUuidMorphs('assignee');
            $table->date('asset_eol_date')->nullable();
            $table->boolean('eol_explicit')->default(false);
            $table->string('order_number', 191)->nullable();
            $table->text('notes')->nullable();
            $table->text('image')->nullable();
            $table->boolean('physical')->default(true);
            $table->boolean('archived')->default(false)->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->boolean('depreciate')->nullable();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('requestable')->default(false);
            $table->foreignUuid('rtd_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('accepted', 191)->nullable();
            $table->dateTime('last_checkout')->nullable();
            $table->dateTime('last_checkin')->nullable();
            $table->dateTime('expected_checkin')->nullable();
            $table->dateTime('last_audit_date')->nullable();
            $table->date('next_audit_date')->nullable();
            $table->unsignedInteger('checkin_counter')->default(0);
            $table->unsignedInteger('checkout_counter')->default(0);
            $table->unsignedInteger('requests_counter')->default(0);
            $table->boolean('byod')->default(false)->nullable();
            $table->softDeletes();
            $table->creator();
        });

        DB::table('assets')->whereNotNull('owner_id')->update([
            'assignee_type' => User::class,
            'assignee_id' => DB::raw('owner_id'),
        ]);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['buy_type', 'guarantee_end', 'invoice']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->nullable()->after('status_id')->constrained('users')->nullOnDelete();
            $table->string('buy_type')->nullable();
            $table->date('guarantee_end')->nullable();
            $table->string('invoice')->nullable();
        });

        // Only user assignees can go back; locations and assets have no owner column.
        DB::table('assets')->where('assignee_type', User::class)->update(['owner_id' => DB::raw('assignee_id')]);

        // The warranty end comes back where the months were set. Computed per
        // row in PHP, the date functions differ between databases.
        DB::table('assets')->whereNotNull('purchase_date')->whereNotNull('warranty_months')
            ->select('id', 'purchase_date', 'warranty_months')->orderBy('id')
            ->each(fn ($a) => DB::table('assets')->where('id', $a->id)->update([
                'guarantee_end' => now()->parse($a->purchase_date)->addMonthsNoOverflow((int) $a->warranty_months)->toDateString(),
            ]));

        Schema::table('assets', function (Blueprint $table) {
            $table->dropCreator();
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('rtd_location_id');
            $table->dropMorphs('assignee');
            $table->dropColumn([
                'name', 'asset_tag', 'asset_eol_date', 'eol_explicit', 'order_number', 'notes', 'image',
                'physical', 'archived', 'warranty_months', 'depreciate', 'requestable', 'accepted',
                'last_checkout', 'last_checkin', 'expected_checkin', 'last_audit_date', 'next_audit_date',
                'checkin_counter', 'checkout_counter', 'requests_counter', 'byod',
            ]);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->double('purchase_cost')->nullable()->change();
            $table->renameColumn('serial', 'serial_number');
            $table->renameColumn('purchase_date', 'buy_date');
            $table->renameColumn('purchase_cost', 'buy_price');
        });
    }
};
