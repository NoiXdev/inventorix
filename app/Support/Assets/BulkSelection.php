<?php

namespace App\Support\Assets;

use App\Models\Asset;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The asset picker of the bulk pages (check out, check in, edit): which
 * assets can be picked, and the selection carried over from the asset list
 * as ?ids=a,b,c, with the ones that cannot be used listed and why.
 */
final class BulkSelection
{
    /** The most assets one bulk action takes. */
    public const MAX_ASSETS = 500;

    /**
     * @param  Builder<Asset>  $available  the assets that can be picked
     * @param  Closure(Asset): ?string  $blocker  why a carried-over asset cannot be used, null when it can
     * @return array{availableAssets: list<array<string, mixed>>, preselectedIds: list<string>, skipped: list<array{name: string, reason: string}>, maxAssets: int}
     */
    public static function props(Request $request, Builder $available, Closure $blocker): array
    {
        $ids = array_values(array_filter(
            explode(',', (string) $request->query('ids', '')),
            fn (string $id) => Str::isUuid($id),
        ));
        $preselected = $ids ? Asset::query()->with('status')->whereKey($ids)->get() : collect();
        $reasons = $preselected->mapWithKeys(fn (Asset $a) => [$a->id => $blocker($a)]);

        return [
            'availableAssets' => $available
                ->with(['status', 'model', 'assignee'])
                ->get()
                ->sortBy(fn (Asset $a) => mb_strtolower($a->displayName()))
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'name' => $a->displayName(),
                    'asset_tag' => $a->asset_tag,
                    'serial' => $a->serial,
                    'model_name' => $a->model?->name,
                    'status_name' => $a->status?->name,
                    'assignee_name' => $a->assigneeName(),
                ])->values()->all(),
            'preselectedIds' => $preselected->filter(fn (Asset $a) => $reasons[$a->id] === null)->pluck('id')->values()->all(),
            'skipped' => $preselected->filter(fn (Asset $a) => $reasons[$a->id] !== null)
                ->map(fn (Asset $a) => ['name' => $a->displayName(), 'reason' => $reasons[$a->id]])
                ->values()->all(),
            'maxAssets' => self::MAX_ASSETS,
        ];
    }

    /** @return array<string, list<mixed>> validation rules for the selected asset_ids */
    public static function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ASSETS],
            'asset_ids.*' => ['uuid', 'distinct', Rule::exists('assets', 'id')->whereNull('deleted_at')],
        ];
    }
}
