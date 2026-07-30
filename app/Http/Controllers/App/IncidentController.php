<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\IncidentRequest;
use App\Models\Asset;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;

class IncidentController extends Controller
{
    public function store(IncidentRequest $request, Asset $asset): RedirectResponse
    {
        $asset->incidents()->create($request->validated());

        return back()->with('success', 'Incident created.');
    }

    public function update(IncidentRequest $request, Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update($request->validated());

        return back()->with('success', 'Incident updated.');
    }

    public function destroy(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->delete();

        return back()->with('success', 'Incident deleted.');
    }

    public function close(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update(['closed_date' => now()]);

        return back()->with('success', 'Incident closed.');
    }

    public function reopen(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update(['closed_date' => null]);

        return back()->with('success', 'Incident reopened.');
    }

    private function guard(Asset $asset, Incident $incident): void
    {
        abort_unless($incident->asset_id === $asset->id, 403);
    }
}
