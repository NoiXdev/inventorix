<?php

namespace App\Services;

use App\Exceptions\AccessoryCheckoutException;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\LicenseSeatException;
use App\Exceptions\OffboardingException;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Offboarding a user: everything they hold (assets, license seats,
 * accessories) is checked in or transferred to another user in one go, and
 * their login can be turned off. All or nothing: if one item fails, nothing
 * changes and the exception names the item.
 */
class OffboardingService
{
    public function __construct(
        private readonly CheckoutService $assets,
        private readonly LicenseService $licenses,
        private readonly AccessoryService $accessories,
    ) {}

    /**
     * @param  list<array{type: 'asset'|'license_seat'|'accessory_checkout', id: string, action: 'checkin'|'transfer'}>  $items
     * @return array{checked_in: int, transferred: int}
     *
     * @throws OffboardingException
     */
    public function run(
        User $user,
        array $items,
        ?User $transferTo,
        ?string $statusId,
        ?string $locationId,
        ?string $note,
        bool $disableLogin,
    ): array {
        $note = 'Offboarding of '.($user->name ?? $user->email ?? '—')
            .($note !== null && trim($note) !== '' ? ': '.trim($note) : '');

        return DB::transaction(function () use ($user, $items, $transferTo, $statusId, $locationId, $note, $disableLogin): array {
            $counts = ['checked_in' => 0, 'transferred' => 0];

            foreach ($items as $item) {
                $transfer = $item['action'] === 'transfer';
                if ($transfer && $transferTo === null) {
                    throw new OffboardingException('Choose who gets the transferred items.');
                }

                match ($item['type']) {
                    'asset' => $this->asset($user, $item['id'], $transfer ? $transferTo : null, $statusId, $locationId, $note),
                    'license_seat' => $this->seat($user, $item['id'], $transfer ? $transferTo : null, $note),
                    'accessory_checkout' => $this->accessory($user, $item['id'], $transfer ? $transferTo : null, $note),
                };

                $counts[$transfer ? 'transferred' : 'checked_in']++;
            }

            if ($disableLogin) {
                $user->login_enabled = false;
                // Ends "remember me" logins; EnsureLoginEnabled ends open sessions.
                $user->setRememberToken(Str::random(60));
                $user->save();
            }

            return $counts;
        });
    }

    private function asset(User $user, string $id, ?User $transferTo, ?string $statusId, ?string $locationId, string $note): void
    {
        $asset = Asset::query()->whereKey($id)->lockForUpdate()->first();
        if ($asset === null || $asset->assignee_type !== User::class || $asset->assignee_id !== $user->id) {
            throw $this->notHeld($asset?->displayName() ?? 'An asset', $user);
        }

        try {
            if ($transferTo === null) {
                $this->assets->checkIn($asset, statusId: $statusId, locationId: $locationId, note: $note);

                return;
            }
            // A transfer keeps the asset's status and location (even none).
            $this->assets->checkIn($asset, note: $note, keepLocation: true);
            $this->assets->checkOut($asset, $transferTo, note: $note);
        } catch (CheckoutNotAllowedException $e) {
            throw $this->failed($asset->displayName(), $e);
        }
    }

    private function seat(User $user, string $id, ?User $transferTo, string $note): void
    {
        $seat = LicenseSeat::query()->whereKey($id)->lockForUpdate()->first();
        $name = $seat?->license?->name ?? 'A license seat';
        if ($seat === null || $seat->assigned_user_id !== $user->id) {
            throw $this->notHeld($name, $user);
        }

        try {
            // A transfer hands on the same seat: never retired, no free seat needed.
            $transferTo === null
                ? $this->licenses->checkIn($seat, $note)
                : $this->licenses->transfer($seat, $transferTo, $note);
        } catch (LicenseSeatException $e) {
            throw $this->failed($name, $e);
        }
    }

    private function accessory(User $user, string $id, ?User $transferTo, string $note): void
    {
        $checkout = AccessoryCheckout::query()->whereKey($id)->lockForUpdate()->first();
        $name = $checkout?->accessory?->name ?? 'An accessory';
        if ($checkout === null || $checkout->assigned_type !== User::class || $checkout->assigned_id !== $user->id) {
            throw $this->notHeld($name, $user);
        }

        try {
            $this->accessories->checkIn($checkout, $note);
            if ($transferTo !== null) {
                $this->accessories->checkOut($checkout->accessory, $transferTo, 1, $note);
            }
        } catch (AccessoryCheckoutException $e) {
            throw $this->failed($name, $e);
        }
    }

    private function notHeld(string $item, User $user): OffboardingException
    {
        return new OffboardingException("{$item} is no longer checked out to ".($user->name ?? $user->email ?? 'this user').'.');
    }

    private function failed(string $item, \Throwable $e): OffboardingException
    {
        return new OffboardingException("{$item}: {$e->getMessage()} Nothing was changed.", previous: $e);
    }
}
