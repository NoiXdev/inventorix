<?php

namespace App\Http\Controllers\App;

use App\Exceptions\ConsumableCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ConsumableCheckoutRequest;
use App\Models\Consumable;
use App\Services\ConsumableService;
use Illuminate\Http\RedirectResponse;

class ConsumableCheckoutController extends Controller
{
    public function __construct(private readonly ConsumableService $consumables) {}

    public function checkout(ConsumableCheckoutRequest $request, Consumable $consumable): RedirectResponse
    {
        try {
            $assignment = $this->consumables->checkOut($consumable, $request->recipient(), (int) $request->validated('qty'), $request->validated('note'));
        } catch (ConsumableCheckoutException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('success', $assignment->qty === 1 ? 'Consumable checked out.' : "{$assignment->qty} checked out.");
    }
}
