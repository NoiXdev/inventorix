<?php

namespace App\Http\Controllers\App;

use App\Exceptions\KitCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\KitCheckoutRequest;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\License;
use App\Services\KitCheckoutService;
use App\Support\Assets\CheckoutOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Checking a whole kit out to a user in one step. */
class KitCheckoutController extends Controller
{
    public function create(Request $request, Kit $kit): Response
    {
        $lines = $kit->lines();
        $blocked = KitCheckoutRequest::permissionBlockers($request->user(), $lines);
        if ($lines->isEmpty()) {
            $blocked[] = 'The kit is empty.';
        }

        $rows = $lines->map(function (KitItem $line) use (&$blocked): array {
            $name = $line->item?->name;
            $row = ['id' => $line->id, 'type' => $line->typeKey(), 'name' => $name, 'qty' => $line->qty, 'available' => $line->isAvailable()];

            if ($line->typeKey() === 'model') {
                $candidates = $row['available'] ? $this->candidates($line->item_id) : [];
                $row['candidates'] = $candidates;
                $row['preselected'] = array_column(array_slice($candidates, 0, $line->qty), 'value');
                $row['stock'] = count($candidates);
            } else {
                $row['stock'] = $row['available'] ? $this->stock($line->item) : 0;
            }

            if (! $row['available']) {
                $blocked[] = ($name ?? 'An item').' is no longer available.';
            } elseif ($row['stock'] < $line->qty) {
                $blocked[] = "Only {$row['stock']} of {$name} available; the kit needs {$line->qty}.";
            }

            return $row;
        })->values()->all();

        return Inertia::render('kits/checkout', [
            'kit' => $kit->only(['id', 'name']),
            'lines' => $rows,
            'blocked' => $blocked,
            'userOptions' => CheckoutOptions::users(),
            'userCreateUrl' => route('app.users.quick-store'),
        ]);
    }

    public function store(KitCheckoutRequest $request, Kit $kit, KitCheckoutService $kits): RedirectResponse
    {
        $user = $request->recipient();

        try {
            $counts = $kits->checkOut($kit, $user, $request->assetIds(), $request->validated('note'));
        } catch (KitCheckoutException $e) {
            throw ValidationException::withMessages(['kit' => $e->getMessage()]);
        }

        $parts = collect([
            'asset' => $counts['assets'], 'license' => $counts['licenses'],
            'accessory' => $counts['accessories'], 'consumable' => $counts['consumables'],
        ])->filter()->map(fn (int $n, string $noun) => "{$n} ".($n === 1 ? $noun : ($noun === 'accessory' ? 'accessories' : "{$noun}s")));

        return to_route('app.users.show', $user)->with('success', 'Kit checked out: '.$parts->implode(', ').'.');
    }

    /**
     * Assets of the model that can be checked out: deployable status,
     * unassigned, not archived, not deleted.
     *
     * @return list<array{value: string, label: string}>
     */
    private function candidates(string $modelId): array
    {
        return Asset::query()
            ->where('model_id', $modelId)
            ->whereNull('assignee_id')
            ->where('archived', false)
            ->whereHas('status', fn ($q) => $q->where('deployable', true))
            ->orderBy('asset_tag')->orderBy('id')
            ->get(['id', 'asset_tag', 'name', 'serial'])
            ->map(fn (Asset $a) => [
                'value' => $a->id,
                'label' => implode(' – ', array_filter([$a->asset_tag, $a->name, $a->serial], fn ($v) => $v !== null && $v !== '')) ?: $a->id,
            ])->values()->all();
    }

    /** Free seats of a license, units left of an accessory or consumable (none when archived). */
    private function stock(License|Accessory|Consumable $item): int
    {
        return match (true) {
            $item instanceof License => $item->freeSeats()->count(),
            default => $item->archived ? 0 : $item->remaining(),
        };
    }
}
