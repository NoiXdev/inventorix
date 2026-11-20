<?php

namespace App\Http\Controllers\App;

use App\Exceptions\CheckoutNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetCheckinRequest;
use App\Http\Requests\App\AssetCheckoutRequest;
use App\Models\Asset;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

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
