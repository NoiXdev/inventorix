<?php

use App\Enums\CategoryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asset types become asset categories, and an asset's category now comes from
 * its model. Every type is copied into `categories` with its id kept, so old
 * activity log entries (`asset_type_id`) still resolve.
 *
 * A model without a category gets the type most of its assets had. Assets
 * whose type differed from that, and assets without a model, lose their own
 * type: the category is no longer stored per asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->insertUsing(
            ['id', 'name', 'category_type', 'created_at', 'updated_at'],
            DB::table('asset_types')
                ->select('id', 'name', DB::raw("'".CategoryType::ASSET->value."'"), 'created_at', 'updated_at')
                // Already there after a rollback, which leaves the categories in place.
                ->whereNotIn('id', DB::table('categories')->select('id')),
        );

        $counts = DB::table('assets')
            ->whereNotNull('model_id')
            ->select('model_id', 'asset_type_id', DB::raw('count(*) as assets'))
            ->groupBy('model_id', 'asset_type_id')
            ->orderByDesc('assets')
            ->orderBy('asset_type_id') // ties resolve the same way on every run
            ->get();

        foreach ($counts->unique('model_id') as $row) {
            DB::table('asset_models')
                ->where('id', $row->model_id)
                ->whereNull('category_id')
                ->update(['category_id' => $row->asset_type_id]);
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_type_id');
        });
        Schema::dropIfExists('asset_types');
    }

    public function down(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('asset_types')->insertUsing(
            ['id', 'name', 'created_at', 'updated_at'],
            DB::table('categories')
                ->select('id', 'name', 'created_at', 'updated_at')
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('category_type', CategoryType::ASSET->value)->whereNull('deleted_at'))
                    ->orWhereIn('id', DB::table('asset_models')->whereNotNull('category_id')->select('category_id'))),
        );

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('asset_type_id')->nullable()->after('status_id')->constrained('asset_types')->cascadeOnDelete();
        });

        DB::table('assets')
            ->join('asset_models', 'asset_models.id', '=', 'assets.model_id')
            ->whereNotNull('asset_models.category_id')
            ->update(['assets.asset_type_id' => DB::raw('asset_models.category_id')]);

        // Left nullable: assets without a model category have no type to go
        // back to, and a made-up placeholder would come back as a category.
        // The categories created from types, and the model categories set from
        // them, are left in place: they may have been edited since.
    }
};
