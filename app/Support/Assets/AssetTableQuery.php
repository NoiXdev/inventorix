<?php

namespace App\Support\Assets;

use App\Enums\AssetDue;
use App\Models\Asset;
use App\Models\CustomField;
use App\Models\Location;
use App\Models\User;
use App\Settings\AlertSettings;
use App\Support\CustomFields\AssetCustomFields;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AssetTableQuery
{
    /**
     * Add the standard asset relation joins and configure the shared
     * searchable/sortable/filterable whitelist. Callers set their own select()
     * before calling and choose paginate()/get() after.
     */
    public static function configure(Builder $query, Request $request): TableQuery
    {
        $query
            ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
            ->leftJoin('categories', 'categories.id', '=', 'asset_models.category_id') // the category is the model's
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            // One join per assignee kind; only the matching one finds a row.
            ->leftJoin('users', fn ($j) => $j->on('users.id', '=', 'assets.assignee_id')->where('assets.assignee_type', User::class))
            ->leftJoin('locations as assigned_locations', fn ($j) => $j->on('assigned_locations.id', '=', 'assets.assignee_id')->where('assets.assignee_type', Location::class))
            ->leftJoin('assets as assigned_assets', fn ($j) => $j->on('assigned_assets.id', '=', 'assets.assignee_id')->where('assets.assignee_type', Asset::class))
            ->leftJoin('status_labels', 'status_labels.id', '=', 'assets.status_id');

        // Due / overdue audits and check-ins (Snipe-IT's due lists).
        $due = $request->input('filter.due');
        if (is_string($due) && ($case = AssetDue::tryFrom($due))) {
            $case->apply($query, app(AlertSettings::class));
        }

        // Custom fields shown in the list are searchable and sortable like any
        // other column.
        $custom = AssetCustomFields::listed()->map(fn (CustomField $f) => 'assets.'.$f->db_column)->all();

        return TableQuery::for($query, $request)
            ->searchable(['assets.name', 'assets.asset_tag', 'assets.serial', 'categories.name', 'manufacturers.name', 'asset_models.name', 'users.name', 'assigned_locations.name', ...$custom])
            ->sortable(['assets.name', 'assets.asset_tag', 'status_name', 'category_name', 'manufacturer_name', 'model_name', 'assignee_name', 'assets.serial', 'assets.purchase_cost', 'incidents_count', ...$custom])
            ->filterable([
                'status_id' => 'assets.status_id',
                'category_id' => 'asset_models.category_id',
                'manufacturer_id' => 'asset_models.manufacturer_id',
                'location_id' => 'assets.location_id',
                'archived' => 'assets.archived',
            ]);
    }

    /**
     * The assignee's name for the select list, whatever kind it is. Assets
     * show their name, else tag, else serial (as Asset::displayName()).
     */
    public static function assigneeNameSql(): string
    {
        return 'coalesce(users.name, assigned_locations.name, assigned_assets.name, assigned_assets.asset_tag, assigned_assets.serial)';
    }
}
