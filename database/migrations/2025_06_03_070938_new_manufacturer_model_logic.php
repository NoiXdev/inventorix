<?php

use App\Models\Manufacturer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_models', function (Blueprint $table) {
            $table->foreignIdFor(Manufacturer::class, 'manufacturer_id')->nullable()->constrained()->nullOnDelete();
        });

        // Plain queries, not the models: the models follow today's schema
        // (soft deletes, renamed columns), which this point in time lacks.
        DB::table('assets')->select(['model_id', 'manufacturer_id'])->whereNotNull(['model_id', 'manufacturer_id'])
            ->groupBy(['model_id', 'manufacturer_id'])->get()
            ->each(static function (object $asset): void {
                DB::table('asset_models')->where('id', $asset->model_id)->update(['manufacturer_id' => $asset->manufacturer_id]);
            });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manufacturer_id');
        });
    }
};
