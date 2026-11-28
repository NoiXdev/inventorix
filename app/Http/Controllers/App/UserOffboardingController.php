<?php

namespace App\Http\Controllers\App;

use App\Exceptions\OffboardingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\OffboardingRequest;
use App\Models\AccessoryCheckout;
use App\Models\ConsumableAssignment;
use App\Models\LicenseSeat;
use App\Models\User;
use App\Services\OffboardingService;
use App\Support\Assets\CheckoutOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The offboarding page of a user: check in or transfer everything they hold
 * in one go, and optionally turn off their login.
 */
class UserOffboardingController extends Controller
{
    public function create(Request $request, User $user): Response
    {
        $actor = $request->user();
        abort_unless(self::mayOffboard($actor), 403);

        $user->load('department', 'assets.model', 'assets.status', 'assets.location');
        $options = CheckoutOptions::for(null);

        return Inertia::render('users/offboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'login_enabled' => $user->login_enabled,
                'department_name' => $user->department?->name,
            ],
            'assets' => $user->assets->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->displayName(),
                'asset_tag' => $a->asset_tag,
                'model_name' => $a->model?->name,
                'serial' => $a->serial,
                'status_name' => $a->status?->name,
                'location_name' => $a->location?->name,
            ])->values()->all(),
            // Null hides the section, as on the user page.
            'licenses' => $actor->can('licenses.view') || $actor->can('licenses.checkin') ? LicenseSeat::heldBy($user) : null,
            'accessories' => $actor->can('accessories.view') || $actor->can('accessories.checkin') ? AccessoryCheckout::heldBy($user) : null,
            'consumables' => $actor->can('consumables.view') ? ConsumableAssignment::handedTo($user) : null,
            'abilities' => collect(OffboardingRequest::MODULES)->mapWithKeys(fn (string $module) => [$module => [
                'checkin' => $actor->can("{$module}.checkin"),
                'transfer' => $actor->can("{$module}.checkin") && $actor->can("{$module}.checkout"),
            ]])->all(),
            'canDisableLogin' => $user->login_enabled && OffboardingRequest::disableLoginBlocker($actor, $user) === null,
            'transferOptions' => array_values(array_filter(CheckoutOptions::users(), fn (array $o) => $o['value'] !== $user->id)),
            'userCreateUrl' => $options['userCreateUrl'],
            'statusOptions' => $options['statusOptions'],
            'locationOptions' => $options['locationOptions'],
            'checkinStatusId' => $options['checkinStatusId'],
        ]);
    }

    /** OffboardingRequest::authorize() refuses actors who may not check anything in. */
    public function store(OffboardingRequest $request, User $user, OffboardingService $offboarding): RedirectResponse
    {
        try {
            $counts = $offboarding->run(
                $user,
                array_values((array) $request->validated('items', [])),
                $request->transferTo(),
                $request->validated('status_id'),
                $request->validated('location_id'),
                $request->validated('note'),
                $request->boolean('disable_login'),
            );
        } catch (OffboardingException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return to_route('app.users.show', $user)
            ->with('success', "Offboarding done: {$counts['checked_in']} checked in, {$counts['transferred']} transferred.");
    }

    /** Whether the actor may check anything in, which the offboarding page is about. */
    public static function mayOffboard(User $actor): bool
    {
        return collect(OffboardingRequest::MODULES)->contains(fn (string $module) => $actor->can("{$module}.checkin"));
    }
}
