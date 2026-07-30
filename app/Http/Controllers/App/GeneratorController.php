<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GeneratorController extends Controller
{
    private const MAX = 1000;

    public function index(): Response
    {
        return Inertia::render('qr-generator/index');
    }

    public function download(Request $request): HttpResponse
    {
        $codes = $this->freshUuids($this->amount($request));

        return response(implode("\n", $codes), 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="generated.txt"',
        ]);
    }

    public function codes(Request $request): JsonResponse
    {
        return response()->json(['uuids' => $this->freshUuids($this->amount($request))]);
    }

    private function amount(Request $request): int
    {
        return max(1, min(self::MAX, (int) $request->input('amount', 20)));
    }

    /** @return array<int, string> */
    private function freshUuids(int $amount): array
    {
        $codes = [];
        while (count($codes) < $amount) {
            $uuid = (string) Str::uuid();
            if (! Asset::query()->whereKey($uuid)->exists()) {
                $codes[] = $uuid;
            }
        }

        return $codes;
    }
}
