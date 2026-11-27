<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CheckoutAcceptance;
use App\Services\AcceptanceService;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Acceptances: pending, accepted and declined (Snipe-IT's unaccepted assets report), with reminders. */
class AcceptanceController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('filter.status', 'pending');
        $query = CheckoutAcceptance::query()->with('user')
            ->when($status === 'pending', fn ($q) => $q->pending())
            ->when($status === 'accepted', fn ($q) => $q->whereNotNull('accepted_at'))
            ->when($status === 'declined', fn ($q) => $q->whereNotNull('declined_at'))
            ->latest();

        $rows = TableQuery::for($query, $request)->searchable(['item_name'])->paginate();
        $rows->getCollection()->transform(fn (CheckoutAcceptance $a) => [
            'id' => $a->id,
            'item_type' => $a->item_type,
            'item_name' => $a->item_name,
            'item_url' => $a->itemUrl(),
            'user_name' => $a->user?->name ?? $a->user?->email,
            'user_url' => $a->user_id ? "/app/users/{$a->user_id}" : null,
            'can_remind' => $a->isPending() && filled($a->user?->email),
            'status' => $a->status(),
            'sent_at' => ($a->reminded_at ?? $a->sent_at)?->format('Y-m-d'),
            'answered_at' => ($a->accepted_at ?? $a->declined_at)?->format('Y-m-d'),
            'note' => $a->note,
        ]);

        return Inertia::render('acceptances/index', [
            'acceptances' => [
                'data' => $rows->items(),
                'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()],
            ],
            'status' => in_array($status, ['pending', 'accepted', 'declined', 'all'], true) ? $status : 'pending',
        ]);
    }

    public function remind(CheckoutAcceptance $acceptance, AcceptanceService $acceptances): RedirectResponse
    {
        return $acceptances->remind($acceptance)
            ? back()->with('success', 'The link was sent again.')
            : back()->with('error', 'Only pending acceptances of users with an email address can be sent again.');
    }
}
