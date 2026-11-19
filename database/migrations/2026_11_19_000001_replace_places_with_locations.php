<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locations replace places: every place becomes a location with the same id,
 * and assets.place_id becomes assets.location_id. Ids are kept so activity log
 * entries that point at a place (place_id / place_changed) still resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 191)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('state', 191)->nullable();
            $table->string('country', 191)->nullable();
            $table->string('address', 191)->nullable();
            $table->string('address2', 191)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignUuid('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('currency', 10)->nullable();
            $table->string('ldap_ou', 191)->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('image', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->creator();
        });

        DB::table('locations')->insertUsing(
            ['id', 'name', 'created_at', 'updated_at'],
            DB::table('places')->select('id', 'name', 'created_at', 'updated_at'),
        );

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('location_id')->nullable()->after('owner_id')->constrained('locations')->nullOnDelete();
        });
        DB::table('assets')->update(['location_id' => DB::raw('place_id')]);

        Schema::table('assets', fn (Blueprint $table) => $table->dropConstrainedForeignId('place_id'));
        Schema::drop('places');
    }

    public function down(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('places')->insertUsing(
            ['id', 'name', 'created_at', 'updated_at'],
            DB::table('locations')->select('id', DB::raw("COALESCE(name, '')"), 'created_at', 'updated_at'),
        );

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('place_id')->nullable()->after('owner_id')->constrained('places')->nullOnDelete();
        });
        DB::table('assets')->update(['place_id' => DB::raw('location_id')]);

        Schema::table('assets', fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
        Schema::drop('locations');
    }
};
