<?php

namespace App\Support\Assets;

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
            ->leftJoin('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->leftJoin('people', 'people.id', '=', 'assets.owner_id');

        return TableQuery::for($query, $request)
            ->searchable(['assets.serial_number', 'asset_types.name', 'manufacturers.name', 'asset_models.name', 'people.name'])
            ->sortable(['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count'])
            ->filterable([
                'state' => 'assets.state',
                'asset_type_id' => 'assets.asset_type_id',
                'manufacturer_id' => 'asset_models.manufacturer_id',
            ]);
    }
}
