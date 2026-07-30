<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\Assets\AssetCsv;
use App\Support\Assets\AssetTableQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetExportController extends Controller
{
    public function index(Request $request): StreamedResponse
    {
        $query = Asset::query()
            ->with(['tags', 'place'])
            ->select(
                'assets.*',
                'asset_types.name as asset_type_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                'people.name as owner_name',
            )
            ->withCount('incidents');

        $assets = AssetTableQuery::configure($query, $request)->get();

        return response()->streamDownload(function () use ($assets): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, AssetCsv::HEADERS);

            foreach ($assets as $asset) {
                fputcsv($out, [
                    $asset->id,
                    $asset->state?->getLabel(),
                    $asset->asset_type_name,
                    $asset->manufacturer_name,
                    $asset->model_name,
                    optional($asset->place)->name,
                    $asset->owner_name,
                    $asset->serial_number,
                    optional($asset->buy_date)->format('Y-m-d'),
                    optional($asset->guarantee_end)->format('Y-m-d'),
                    $asset->buy_price,
                    $asset->buy_type?->getLabel(),
                    $asset->tags->pluck('name')->implode(', '),
                ]);
            }

            fclose($out);
        }, 'assets.csv', ['Content-Type' => 'text/csv']);
    }
}
