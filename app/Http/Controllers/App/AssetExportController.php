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
            ->with(['tags', 'location', 'status', 'supplier'])
            ->select(
                'assets.*',
                'categories.name as category_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                // The users join only matches user assignees: the CSV carries users only.
                'users.name as assigned_user_name',
            )
            ->withCount('incidents');

        $assets = AssetTableQuery::configure($query, $request)->get();

        return response()->streamDownload(function () use ($assets): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, AssetCsv::HEADERS);

            foreach ($assets as $asset) {
                fputcsv($out, [
                    $asset->id,
                    $asset->name,
                    $asset->asset_tag,
                    $asset->status?->name,
                    $asset->category_name,
                    $asset->manufacturer_name,
                    $asset->model_name,
                    $asset->serial,
                    optional($asset->location)->name,
                    $asset->assigned_user_name,
                    optional($asset->purchase_date)->format('Y-m-d'),
                    $asset->purchase_cost,
                    $asset->supplier?->name,
                    $asset->order_number,
                    $asset->warranty_months,
                    $asset->notes,
                    $asset->tags->pluck('name')->implode(', '),
                ]);
            }

            fclose($out);
        }, 'assets.csv', ['Content-Type' => 'text/csv']);
    }
}
