<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetBulkEditRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Support\Assets\BulkSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Edit several assets at once (Snipe-IT's bulk edit). The assignee only changes through check-out and check-in. */
class AssetBulkEditController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('assets/bulk-edit', [
            ...BulkSelection::props($request, Asset::query(), fn (Asset $a) => null),
            'statusOptions' => StatusLabel::options(),
            'modelOptions' => AssetModel::query()->with('manufacturer')->orderBy('name')->get()
                ->map(fn (AssetModel $m) => ['value' => $m->id, 'label' => trim('('.optional($m->manufacturer)->name.') '.$m->name)])->all(),
            'locationOptions' => Location::options(),
            'supplierOptions' => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $s) => ['value' => $s->id, 'label' => $s->name])->all(),
        ]);
    }

    public function update(AssetBulkEditRequest $request): RedirectResponse
    {
        $changes = $request->changes();
        $ids = $request->validated('asset_ids');

        $count = DB::transaction(function () use ($ids, $changes): int {
            $assets = Asset::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($assets as $asset) {
                // forceFill: the bulk fields are an explicit whitelist; saving each
                // one runs the observer (EOL) and records the change in the history.
                $asset->forceFill($changes)->save();
            }

            return $assets->count();
        });

        return to_route('app.assets.index')->with('success', $count === 1 ? '1 asset updated.' : "{$count} assets updated.");
    }
}
