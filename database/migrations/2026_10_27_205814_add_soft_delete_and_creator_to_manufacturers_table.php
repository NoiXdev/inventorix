<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production databases carry stray `deleted_at` and `created_by`
        // (char(36), no foreign key) columns on manufacturers that no migration
        // created and no code uses. deleted_at already matches softDeletes()
        // and is kept; created_by is dropped so creator() can add it as a
        // proper uuid foreign key — but only while it is empty, so no data is
        // ever thrown away silently.
        if (Schema::hasColumn('manufacturers', 'created_by')) {
            if (DB::table('manufacturers')->whereNotNull('created_by')->exists()) {
                throw new RuntimeException('manufacturers.created_by already holds data; migrate it by hand before running this migration.');
            }

            Schema::table('manufacturers', fn (Blueprint $table) => $table->dropColumn('created_by'));
        }

        Schema::table('manufacturers', function (Blueprint $table) {
            if (! Schema::hasColumn('manufacturers', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->creator();
        });
    }

    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropCreator();
        });
    }
};
