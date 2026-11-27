<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetAuditRequest;
use App\Http\Requests\App\AssetAuditScanRequest;
use App\Models\Asset;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AssetAuditController extends Controller
{
    public function __construct(private readonly AuditService $audits) {}

    public function store(AssetAuditRequest $request, Asset $asset): RedirectResponse
    {
        $this->audits->audit(
            $asset,
            locationId: $request->validated('location_id'),
            nextAudit: $this->nextAudit($request->validated('next_audit_date')),
            note: $request->validated('note'),
        );

        return back()->with('success', 'Asset audited.');
    }

    /** The audit scan page (Snipe-IT's quick scan audit). */
    public function scanPage(): Response
    {
        return Inertia::render('assets/audit-scan', [
            'locationOptions' => Location::options(),
            'nextAuditDate' => $this->audits->proposedNextAudit()?->toDateString(),
        ]);
    }

    /** Audit the asset with the scanned tag or serial; answers JSON so scanning goes on. */
    public function scan(AssetAuditScanRequest $request): JsonResponse
    {
        $code = trim($request->validated('code'));
        $matches = Asset::query()
            ->where(fn ($q) => $q->where('asset_tag', $code)->orWhere('serial', $code))
            ->limit(2)->get();

        if ($matches->count() !== 1) {
            return response()->json([
                'message' => $matches->isEmpty()
                    ? "No asset with the tag or serial “{$code}”."
                    : "Several assets have the tag or serial “{$code}”. Audit it from its page.",
            ], 422);
        }

        $asset = $this->audits->audit(
            $matches->first(),
            locationId: $request->validated('location_id'),
            nextAudit: $this->nextAudit($request->validated('next_audit_date')),
            note: $request->validated('note'),
        );

        return response()->json([
            'id' => $asset->id,
            'name' => $asset->displayName(),
            'asset_tag' => $asset->asset_tag,
            'serial' => $asset->serial,
            'location' => $asset->location_id ? Location::paths(withTrashed: true)->get($asset->location_id) : null,
            'next_audit_date' => $asset->next_audit_date?->format('Y-m-d'),
        ]);
    }

    private function nextAudit(?string $date): ?Carbon
    {
        return $date !== null ? Carbon::parse($date)->startOfDay() : null;
    }
}
