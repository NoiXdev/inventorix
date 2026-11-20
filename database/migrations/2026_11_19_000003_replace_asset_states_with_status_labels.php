<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Status labels replace the hard-coded asset states. Each former state becomes
 * a label whose system_key is the old value, so handovers can still find the
 * statuses they move assets between, and old activity log entries (which hold
 * state values) can still be labelled.
 */
return new class extends Migration
{
    /** Former state => [name, type flags, default]. Kept here, not read from app code. */
    private const SEED = [
        'new' => ['Neu', 'deployable', true],
        'storage' => ['Lager', 'deployable', false],
        'in-use' => ['In Benutzung', 'deployable', false],
        'lend' => ['Verliehen', 'deployable', false],
        'need-repair' => ['Benötigt Reparatur', 'undeployable', false],
        'under-repair' => ['In Reparatur', 'undeployable', false],
        'defect' => ['Defekt', 'undeployable', false],
        'sold' => ['Verkauft', 'archived', false],
    ];

    public function up(): void
    {
        Schema::create('status_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->nullable();
            $table->boolean('deployable')->default(false);
            $table->boolean('pending')->default(false);
            $table->boolean('archived')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('show_in_nav')->nullable()->default(false);
            $table->boolean('default_label')->nullable()->default(false);
            // Set on the labels the application relies on (App\Enums\SystemStatus).
            $table->string('system_key', 50)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        $now = now();
        $ids = [];
        foreach (self::SEED as $key => [$name, $type, $default]) {
            $ids[$key] = (string) Str::uuid();
            DB::table('status_labels')->insert([
                'id' => $ids[$key],
                'name' => $name,
                'deployable' => $type === 'deployable',
                'pending' => $type === 'pending',
                'archived' => $type === 'archived',
                'default_label' => $default,
                'show_in_nav' => false,
                'system_key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('status_id')->nullable()->after('state')->constrained('status_labels')->nullOnDelete();
        });
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->foreignUuid('status_from_id')->nullable()->after('state_from')->constrained('status_labels')->nullOnDelete();
            $table->foreignUuid('status_to_id')->nullable()->after('state_to')->constrained('status_labels')->nullOnDelete();
        });

        foreach ($ids as $key => $id) {
            DB::table('assets')->where('state', $key)->update(['status_id' => $id]);
            DB::table('handover_asset')->where('state_from', $key)->update(['status_from_id' => $id]);
            DB::table('handover_asset')->where('state_to', $key)->update(['status_to_id' => $id]);
        }

        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('state'));
        Schema::table('handover_asset', fn (Blueprint $table) => $table->dropColumn(['state_from', 'state_to']));
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->string('state')->default('new')->after('status_id'));
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->string('state_from')->default('new')->after('status_from_id');
            $table->string('state_to')->default('new')->after('status_to_id');
        });

        // Labels created after the migration have no former state to go back to;
        // their assets fall back to the column default.
        foreach (DB::table('status_labels')->whereNotNull('system_key')->pluck('system_key', 'id') as $id => $key) {
            DB::table('assets')->where('status_id', $id)->update(['state' => $key]);
            DB::table('handover_asset')->where('status_from_id', $id)->update(['state_from' => $key]);
            DB::table('handover_asset')->where('status_to_id', $id)->update(['state_to' => $key]);
        }

        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_from_id');
            $table->dropConstrainedForeignId('status_to_id');
        });
        Schema::table('assets', fn (Blueprint $table) => $table->dropConstrainedForeignId('status_id'));
        Schema::drop('status_labels');
    }
};
