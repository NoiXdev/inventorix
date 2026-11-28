<?php

namespace App\Http\Controllers\App;

use App\Models\CheckoutAcceptance;
use App\Services\AcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * A logged-in user answering their own check-out acceptance in the app,
 * the in-app counterpart of the signed mail link.
 */
class MyAcceptanceController
{
    public function show(Request $request, CheckoutAcceptance $acceptance): Response
    {
        $this->own($request, $acceptance);

        return Inertia::render('my-items/acceptance', [
            'acceptance' => [
                'item_name' => $acceptance->item_name,
                'user_name' => $acceptance->user?->name,
                'eula_text' => $acceptance->eula_text,
                'status' => $acceptance->status(),
                'answered_at' => ($acceptance->accepted_at ?? $acceptance->declined_at)?->format('d.m.Y H:i'),
            ],
            'submitUrl' => route('app.my-items.acceptances.respond', $acceptance),
        ]);
    }

    public function respond(Request $request, CheckoutAcceptance $acceptance, AcceptanceService $acceptances): RedirectResponse
    {
        $this->own($request, $acceptance);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'signature_png' => ['nullable', 'string', 'max:400000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $acceptances->respond($acceptance, $data['decision'] === 'accept', $data['signature_png'] ?? null, $data['note'] ?? null, $request->ip(), $request->userAgent());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return to_route('app.my-items')->with('success', 'Thank you, your answer was saved.');
    }

    private function own(Request $request, CheckoutAcceptance $acceptance): void
    {
        abort_unless($acceptance->user_id === $request->user()->id, 404);
    }
}
