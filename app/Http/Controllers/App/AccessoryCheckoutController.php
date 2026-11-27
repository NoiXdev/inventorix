<?php

namespace App\Http\Controllers\App;

use App\Exceptions\AccessoryCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AccessoryCheckoutRequest;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Services\AccessoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccessoryCheckoutController extends Controller
{
    public function __construct(private readonly AccessoryService $accessories) {}

    public function checkout(AccessoryCheckoutRequest $request, Accessory $accessory): RedirectResponse
    {
        try {
            $qty = $this->accessories->checkOut($accessory, $request->target(), (int) $request->validated('qty'), $request->validated('note'));
        } catch (AccessoryCheckoutException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('success', $qty === 1 ? 'Accessory checked out.' : "{$qty} checked out.");
    }

    public function checkin(Request $request, AccessoryCheckout $checkout): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:5000']])['note'] ?? null;
        try {
            $this->accessories->checkIn($checkout, $note);
        } catch (AccessoryCheckoutException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Accessory checked in.');
    }
}
