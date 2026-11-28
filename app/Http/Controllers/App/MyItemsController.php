<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\ConsumableAssignment;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** What the logged-in user holds right now. Open to every user; only their own items are shown. */
class MyItemsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $link = fn (string $permission, string $path, string $id): ?string => $user->can($permission) ? "/app/{$path}/{$id}" : null;

        $assets = Asset::query()
            ->where('assignee_type', User::class)->where('assignee_id', $user->id)
            ->with('model')->orderBy('name')->get();

        $latest = CheckoutAcceptance::query()
            ->where('user_id', $user->id)->where('item_type', 'asset')->whereIn('item_id', $assets->modelKeys())
            ->orderBy('created_at')->get()
            ->groupBy('item_id')->map(fn ($rows) => $rows->last()->status());

        return Inertia::render('my-items/index', [
            'assets' => $assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'asset_tag' => $a->asset_tag,
                'model' => $a->model?->name,
                'serial' => $a->serial,
                'checked_out_at' => $a->last_checkout?->format('Y-m-d'),
                'acceptance' => $latest[$a->id] ?? null,
                'href' => $link('assets.view', 'assets', $a->id),
            ])->values()->all(),
            'licenses' => collect(LicenseSeat::heldBy($user))->map(fn (array $s) => [
                'name' => $s['name'],
                'checked_out_at' => $s['checked_out_at'],
                'href' => $link('licenses.view', 'licenses', $s['license_id']),
            ])->all(),
            'accessories' => collect(AccessoryCheckout::heldBy($user))->groupBy('accessory_id')->map(fn ($rows, $id) => [
                'name' => $rows[0]['name'],
                'qty' => $rows->count(),
                'checked_out_at' => $rows->pluck('checked_out_at')->filter()->min(),
                'href' => $link('accessories.view', 'accessories', $id),
            ])->values()->all(),
            'consumables' => collect(ConsumableAssignment::handedTo($user))->map(fn (array $c) => [
                'name' => $c['name'],
                'qty' => $c['qty'],
                'checked_out_at' => $c['checked_out_at'],
                'href' => $c['deleted'] ? null : $link('consumables.view', 'consumables', $c['consumable_id']),
            ])->all(),
            'pendingAcceptances' => CheckoutAcceptance::query()->pending()->where('user_id', $user->id)
                ->orderBy('sent_at')->get()
                ->map(fn (CheckoutAcceptance $a) => [
                    'id' => $a->id,
                    'item_name' => $a->item_name,
                    'sent_at' => $a->sent_at?->format('Y-m-d'),
                ])->all(),
        ]);
    }
}
