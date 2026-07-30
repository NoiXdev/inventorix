<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScanController extends Controller
{
    public function resolve(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');

        if (! Str::isUuid($code)) {
            return back()->with('error', 'Ungültiger QR-Code.');
        }

        if (Asset::query()->whereKey($code)->exists()) {
            return to_route('app.assets.show', $code);
        }

        return to_route('app.assets.create', ['forceId' => $code]);
    }
}
