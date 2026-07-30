<?php

namespace App\Http\Controllers\App;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Exceptions\HandoverStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\HandoverRequest;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\Person;
use App\Services\HandoverService;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $handovers = TableQuery::for(
            Handover::query()->withCount('assets')->with('createdBy')->latest('signed_at'),
            $request,
        )->searchable(['recipient_name'])->sortable(['signed_at', 'type', 'recipient_name', 'assets_count'])->paginate();

        $handovers->getCollection()->transform(fn (Handover $h) => [
            'id' => $h->id,
            'type' => $h->type->value,
            'type_label' => $h->type->getLabel(),
            'recipient_name' => $h->recipient_name,
            'recipient_kind_label' => $h->recipient_kind->getLabel(),
            'assets_count' => $h->assets_count,
            'created_by_name' => optional($h->createdBy)->name,
            'signed_at' => optional($h->signed_at)->toDateTimeString(),
            'pdf_ready' => (bool) $h->pdf_path,
            'pdf_url' => $h->pdf_path ? URL::temporarySignedRoute('handover.pdf', now()->addMinutes(5), ['handover' => $h->id]) : null,
        ]);

        return Inertia::render('handovers/index', [
            'handovers' => [
                'data' => $handovers->items(),
                'meta' => [
                    'current_page' => $handovers->currentPage(), 'last_page' => $handovers->lastPage(),
                    'per_page' => $handovers->perPage(), 'total' => $handovers->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('handovers/create', [
            'typeOptions' => array_map(fn (HandoverType $t) => [
                'value' => $t->value, 'label' => $t->getLabel(),
                'allowedStates' => array_map(fn ($s) => $s->value, $t->allowedStateFrom()),
                'assignsOwner' => $t->assignsRecipientAsOwner(),
            ], HandoverType::cases()),
            'recipientKindOptions' => array_map(fn (RecipientKind $k) => ['value' => $k->value, 'label' => $k->getLabel()], RecipientKind::cases()),
            'personOptions' => Person::query()->orderBy('name')->get()
                ->map(fn (Person $p) => ['value' => $p->id, 'label' => $p->name, 'email' => $p->email])->all(),
            'assetOptions' => Asset::query()->with('assetType', 'model.manufacturer')->get()
                ->map(fn (Asset $a) => [
                    'value' => $a->id,
                    'label' => '('.optional(optional($a->model)->manufacturer)->name.') '.optional($a->model)->name.' — '.($a->serial_number ?? '—'),
                    'state' => $a->state->value,
                ])->all(),
            'defaultTerms' => (string) config('handover.terms'),
        ]);
    }

    public function store(HandoverRequest $request, HandoverService $service): RedirectResponse
    {
        $v = $request->validated();
        $data = new HandoverData(
            type: HandoverType::from($v['type']),
            recipientKind: RecipientKind::from($v['recipient_kind']),
            recipientPersonId: $v['recipient_kind'] === RecipientKind::INTERNAL->value ? ($v['recipient_person_id'] ?? null) : null,
            recipientName: $v['recipient_name'],
            recipientEmail: $v['recipient_email'] ?? null,
            assetIds: $v['asset_ids'],
            accessories: $v['accessories'] ?? null,
            conditionNotes: $v['condition_notes'] ?? null,
            termsText: $v['terms_text'],
            signaturePngBase64: $v['signature_png'],
            signatureIp: $request->ip(),
            signatureUserAgent: (string) $request->userAgent(),
            createdById: (string) $request->user()->id,
        );

        try {
            $handover = $service->commit($data);
        } catch (HandoverStateConflictException) {
            return back()->withErrors(['asset_ids' => 'One or more selected assets are no longer in an allowed state.']);
        } catch (\InvalidArgumentException) {
            // HandoverService::commit -> decodeSignature() throws when the signature payload
            // isn't valid base64, exceeds the max byte size, or isn't a PNG; map it to signature_png.
            return back()->withErrors(['signature_png' => 'The signature could not be read. Please sign again.']);
        }

        return to_route('app.handovers.show', $handover->id)->with('success', 'Handover created.');
    }

    public function show(Handover $handover): Response
    {
        $handover->load('recipientPerson', 'createdBy', 'assets.model.manufacturer', 'assets.assetType');

        return Inertia::render('handovers/show', [
            'handover' => [
                'id' => $handover->id,
                'type_label' => $handover->type->getLabel(),
                'recipient_name' => $handover->recipient_name,
                'recipient_email' => $handover->recipient_email,
                'recipient_kind_label' => $handover->recipient_kind->getLabel(),
                'accessories' => $handover->accessories,
                'condition_notes' => $handover->condition_notes,
                'terms_text' => $handover->terms_text,
                'created_by_name' => optional($handover->createdBy)->name,
                'signed_at' => optional($handover->signed_at)->toDateTimeString(),
                'pdf_ready' => (bool) $handover->pdf_path,
                'pdf_url' => $handover->pdf_path ? URL::temporarySignedRoute('handover.pdf', now()->addMinutes(5), ['handover' => $handover->id]) : null,
            ],
            'assets' => $handover->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'label' => '('.optional(optional($a->model)->manufacturer)->name.') '.optional($a->model)->name,
                'state_from' => AssetState::tryFrom($a->pivot->state_from)?->getLabel() ?? $a->pivot->state_from,
                'state_to' => AssetState::tryFrom($a->pivot->state_to)?->getLabel() ?? $a->pivot->state_to,
            ])->all(),
        ]);
    }
}
