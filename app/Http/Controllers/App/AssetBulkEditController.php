<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusLabelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetBulkEditRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Services\CheckoutService;
use App\Support\Assets\BulkSelection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function update(AssetBulkEditRequest $request, CheckoutService $checkouts): RedirectResponse
    {
        $changes = $request->changes();
        $ids = $request->validated('asset_ids');

        $count = DB::transaction(function () use ($ids, $changes, $checkouts): int {
            $assets = Asset::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $this->guardCheckedOut($assets, $changes);

            foreach ($assets as $asset) {
                // forceFill: the bulk fields are an explicit whitelist; saving each
                // one runs the observer (EOL) and records the change in the history.
                $asset->forceFill($changes)->save();
                // Assets checked out to this one stay where it is.
                if (array_key_exists('location_id', $changes)) {
                    $checkouts->moveAssignedAssets($asset);
                }
            }

            return $assets->count();
        });

        return to_route('app.assets.index')->with('success', $count === 1 ? '1 asset updated.' : "{$count} assets updated.");
    }

    /**
     * A checked-out asset keeps a status it could be checked out with: the
     * new status must be deployable or pending, and it cannot be archived
     * (Snipe-IT skips such assets silently; here the edit is refused and
     * the assets are named, so nothing changes half-way).
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, mixed>  $changes
     */
    private function guardCheckedOut(Collection $assets, array $changes): void
    {
        $out = $assets->filter(fn (Asset $a) => $a->assignee_id !== null);
        if ($out->isEmpty()) {
            return;
        }
        $names = $out->map(fn (Asset $a) => $a->displayName())->implode(', ');

        if (isset($changes['status_id'])) {
            $type = StatusLabel::query()->findOrFail($changes['status_id'])->type;
            if (! in_array($type, [StatusLabelType::DEPLOYABLE, StatusLabelType::PENDING], true)) {
                throw ValidationException::withMessages([
                    'status_id' => "Checked-out assets need a deployable or pending status. Check these in first: {$names}.",
                ]);
            }
        }
        if (($changes['archived'] ?? false) === true) {
            throw ValidationException::withMessages([
                'archived' => "Checked-out assets cannot be archived. Check these in first: {$names}.",
            ]);
        }
    }
}
