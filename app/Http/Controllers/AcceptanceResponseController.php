<?php

namespace App\Http\Controllers;

use App\Models\CheckoutAcceptance;
use App\Services\AcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The page a user reaches through the signed link in the check-out mail:
 * no login, the signature of the link is the access control.
 */
class AcceptanceResponseController
{
    public function show(Request $request, CheckoutAcceptance $acceptance): Response
    {
        $expires = Carbon::createFromTimestamp((int) $request->query('expires'));

        return Inertia::render('acceptance/respond', [
            'acceptance' => [
                'item_name' => $acceptance->item_name,
                'user_name' => $acceptance->user?->name,
                'eula_text' => $acceptance->eula_text,
                'status' => $acceptance->status(),
                'answered_at' => ($acceptance->accepted_at ?? $acceptance->declined_at)?->format('d.m.Y H:i'),
            ],
            // Answering posts back under a signature that expires with the link.
            'submitUrl' => URL::temporarySignedRoute('acceptances.respond', $expires, ['acceptance' => $acceptance->id]),
        ]);
    }

    public function respond(Request $request, CheckoutAcceptance $acceptance, AcceptanceService $acceptances): RedirectResponse
    {
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

        return back();
    }
}
