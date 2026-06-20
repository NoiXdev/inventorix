<?php

namespace App\Services;

use App\DataObjects\WarrantyScanResult;
use App\Models\Asset;
use App\Models\WarrantyNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WarrantyScanner
{
    /** @var array<int, int> */
    private array $leadDays;

    /** @param array<int, int> $leadDays */
    public function __construct(array $leadDays)
    {
        // Ascending so the first crossed lead is the tightest one.
        $this->leadDays = collect($leadDays)
            ->map(fn ($d): int => (int) $d)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return Collection<int, WarrantyScanResult> */
    public function scan(?CarbonInterface $today = null): Collection
    {
        $today = ($today ? Carbon::instance($today) : Carbon::now())->startOfDay();

        return Asset::query()
            ->whereNotNull('guarantee_end')
            ->with(['owner', 'model'])
            ->get()
            ->map(fn (Asset $asset): ?WarrantyScanResult => $this->resultFor($asset, $today))
            ->filter()
            ->values();
    }

    private function resultFor(Asset $asset, Carbon $today): ?WarrantyScanResult
    {
        $end = $asset->guarantee_end->copy()->startOfDay();
        $daysLeft = (int) round($today->diffInDays($end, false));

        $milestone = $this->milestoneFor($daysLeft);

        if ($milestone === null) {
            return null;
        }

        $alreadySent = WarrantyNotification::query()
            ->where('asset_id', $asset->id)
            ->whereDate('guarantee_end', $end->toDateString())
            ->where('milestone', $milestone)
            ->exists();

        if ($alreadySent) {
            return null;
        }

        return new WarrantyScanResult($asset, $milestone, $daysLeft);
    }

    private function milestoneFor(int $daysLeft): ?string
    {
        if ($daysLeft < 0) {
            return 'expired';
        }

        foreach ($this->leadDays as $lead) {
            if ($daysLeft <= $lead) {
                return (string) $lead;
            }
        }

        return null;
    }
}
