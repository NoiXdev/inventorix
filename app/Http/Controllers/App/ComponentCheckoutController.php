<?php

namespace App\Http\Controllers\App;

use App\Exceptions\ComponentCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ComponentCheckoutRequest;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentAsset;
use App\Services\ComponentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComponentCheckoutController extends Controller
{
    public function __construct(private readonly ComponentService $components) {}

    public function checkout(ComponentCheckoutRequest $request, Component $component): RedirectResponse
    {
        $asset = Asset::query()->findOrFail($request->validated('asset_id'));
        try {
            $this->components->checkOut($component, $asset, (int) $request->validated('qty'), $request->validated('note'));
        } catch (ComponentCheckoutException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('success', 'Component checked out.');
    }

    public function checkin(Request $request, ComponentAsset $componentAsset): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $this->components->checkIn($componentAsset, (int) $data['qty'], $data['note'] ?? null);
        } catch (ComponentCheckoutException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('success', 'Component checked in.');
    }
}
