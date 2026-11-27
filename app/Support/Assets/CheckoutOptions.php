<?php

namespace App\Support\Assets;

use App\Enums\StatusLabelType;
use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;

/** The choices of the check-out and check-in forms. */
final class CheckoutOptions
{
    /**
     * For the forms of one asset, or for a bulk checkout when $asset is null.
     *
     * @return array<string, mixed>
     */
    public static function for(?Asset $asset): array
    {
        $statuses = StatusLabel::options();

        return [
            'assigneeOptions' => self::assignees($asset),
            'userCreateUrl' => route('app.users.quick-store'),
            'deployableStatusOptions' => array_values(array_filter($statuses, fn (array $s) => $s['type'] === StatusLabelType::DEPLOYABLE->value)),
            'statusOptions' => $statuses,
            'locationOptions' => Location::options(),
            // Preselected: a checkout puts the asset in use, a check-in into
            // storage, as handovers do.
            'checkoutStatusId' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'checkinStatusId' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'rtdLocationName' => $asset?->rtd_location_id ? Location::paths(withTrashed: true)->get($asset->rtd_location_id) : null,
        ];
    }

    /**
     * Who or what an asset can be checked out to.
     *
     * @param  list<array{value: string, label: string}>  $locations
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function assignees(?Asset $asset, array $locations = []): array
    {
        return [
            'user' => User::query()->orderBy('name')->get()
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name ?? $u->email ?? '—'])->all(),
            'location' => $locations ?: Location::options(),
            // Any other live asset; the service refuses an asset assigned to this one.
            'asset' => Asset::query()->when($asset, fn ($q) => $q->whereKeyNot($asset->id))->get()
                ->sortBy(fn (Asset $a) => mb_strtolower($a->displayName()))
                ->map(fn (Asset $a) => ['value' => $a->id, 'label' => $a->displayName()])->values()->all(),
        ];
    }
}
