<?php

namespace App\Http\Middleware;

use App\Models\StatusLabel;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => ($u = $request->user())
                    ? ['id' => $u->id, 'name' => $u->name ?? $u->email, 'email' => $u->email]
                    : null,
                // Effective permission map (catalogue value => bool) for UI gating.
                'can' => fn () => $request->user()?->effectivePermissions() ?? [],
            ],
            // Status labels marked "show in nav", listed under Assets in the sidebar.
            'statusNav' => fn () => $request->user()
                ? StatusLabel::query()->where('show_in_nav', true)->withCount('assets')->orderBy('name')->get()
                    ->map(fn (StatusLabel $l) => ['id' => $l->id, 'name' => $l->name, 'count' => $l->assets_count])->all()
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
