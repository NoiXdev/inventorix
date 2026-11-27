<?php

namespace App\Http\Controllers\App;

use App\Exceptions\LicenseSeatException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\LicenseCheckoutRequest;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LicenseSeatController extends Controller
{
    public function __construct(private readonly LicenseService $licenses) {}

    public function checkout(LicenseCheckoutRequest $request, License $license): RedirectResponse
    {
        try {
            $this->licenses->checkOut($license, $request->target(), $request->validated('note'));
        } catch (LicenseSeatException $e) {
            return back()->withErrors(['assignee_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Seat checked out.');
    }

    public function checkin(Request $request, LicenseSeat $seat): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:5000']])['note'] ?? null;
        try {
            $this->licenses->checkIn($seat, $note);
        } catch (LicenseSeatException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $seat->license->reassignable ? 'Seat checked in.' : 'Seat checked in; it is used up (the license is not reassignable).');
    }
}
