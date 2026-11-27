<?php

namespace App\Http\Controllers\App;

use App\Exceptions\CheckoutNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetBulkCheckoutRequest;
use App\Http\Requests\App\AssetCheckinRequest;
use App\Http\Requests\App\AssetCheckoutRequest;
use App\Models\Asset;
use App\Services\CheckoutService;
use App\Support\Assets\CheckoutOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetCheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkouts) {}

    public function checkout(AssetCheckoutRequest $request, Asset $asset): RedirectResponse
    {
        try {
            $this->checkouts->checkOut(
                $asset,
                $request->target(),
                statusId: $request->validated('status_id'),
                checkoutAt: $this->date($request->validated('checkout_at')),
                expectedCheckin: ($d = $request->validated('expected_checkin')) ? Carbon::parse($d)->startOfDay() : null,
                note: $request->validated('note'),
            );
        } catch (CheckoutNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('app.assets.show', $asset)->with('success', 'Asset checked out.');
    }

    /**
     * The bulk checkout page. Assets selected in the asset list come along
     * as ?ids=a,b,c; those that cannot be checked out are listed with why.
     */
    public function bulkCreate(Request $request): Response
    {
        $ids = array_values(array_filter(
            explode(',', (string) $request->query('ids', '')),
            fn (string $id) => Str::isUuid($id),
        ));
        $preselected = $ids ? Asset::query()->with('status')->whereKey($ids)->get() : collect();

        return Inertia::render('assets/bulk-checkout', [
            'availableAssets' => Asset::query()
                ->whereNull('assignee_id')
                ->whereHas('status', fn ($q) => $q->where('deployable', true))
                ->with(['status', 'model'])
                ->get()
                ->sortBy(fn (Asset $a) => mb_strtolower($a->displayName()))
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'name' => $a->displayName(),
                    'asset_tag' => $a->asset_tag,
                    'serial' => $a->serial,
                    'model_name' => $a->model?->name,
                    'status_name' => $a->status?->name,
                ])->values()->all(),
            'preselectedIds' => $preselected->filter(fn (Asset $a) => $this->checkouts->checkoutBlocker($a) === null)->pluck('id')->values()->all(),
            'skipped' => $preselected
                ->map(fn (Asset $a) => ['name' => $a->displayName(), 'reason' => $this->checkouts->checkoutBlocker($a)])
                ->filter(fn (array $s) => $s['reason'] !== null)->values()->all(),
            'checkoutOptions' => CheckoutOptions::for(null),
            'maxAssets' => AssetBulkCheckoutRequest::MAX_ASSETS,
        ]);
    }

    /** Check the assets selected in the asset list out to one target, all or none. */
    public function bulkCheckout(AssetBulkCheckoutRequest $request): RedirectResponse
    {
        try {
            $count = $this->checkouts->checkOutMany(
                $request->validated('asset_ids'),
                $request->target(),
                statusId: $request->validated('status_id'),
                checkoutAt: $this->date($request->validated('checkout_at')),
                expectedCheckin: ($d = $request->validated('expected_checkin')) ? Carbon::parse($d)->startOfDay() : null,
                note: $request->validated('note'),
            );
        } catch (CheckoutNotAllowedException $e) {
            // As a form error, so the dialog stays open with the message.
            return back()->withErrors(['asset_ids' => $e->getMessage()]);
        }

        return to_route('app.assets.index')->with('success', $count === 1 ? '1 asset checked out.' : "{$count} assets checked out.");
    }

    public function checkin(AssetCheckinRequest $request, Asset $asset): RedirectResponse
    {
        if ($asset->assignee_id === null) {
            return back()->with('error', 'The asset is not checked out.');
        }

        $this->checkouts->checkIn(
            $asset,
            statusId: $request->validated('status_id'),
            locationId: $request->validated('location_id'),
            checkinAt: $this->date($request->validated('checkin_at')),
            note: $request->validated('note'),
        );

        return to_route('app.assets.show', $asset)->with('success', 'Asset checked in.');
    }

    /**
     * A date from the form, with the current time on today's date so the
     * order of same-day events stays right.
     */
    private function date(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        $date = Carbon::parse($value);

        return $date->isToday() ? now() : $date->startOfDay();
    }
}
