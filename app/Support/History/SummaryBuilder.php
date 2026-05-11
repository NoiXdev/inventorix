<?php

namespace App\Support\History;

use App\Enums\AssetState;
use App\Models\Incident;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class SummaryBuilder
{
    public function forActivity(Activity $activity): string
    {
        $body = $this->bodyFor($activity);

        if ($activity->subject_type === Incident::class) {
            $prefix = Incident::find($activity->subject_id) === null
                ? trans('history.summary.incident_removed')
                : trans('history.summary.incident_prefix', ['id' => $activity->subject_id]);

            return $prefix . $body;
        }

        return $body;
    }

    private function bodyFor(Activity $activity): string
    {
        return match ($activity->description) {
            'created'        => trans('history.summary.created'),
            'deleted'        => trans('history.summary.deleted'),
            'updated'        => trans('history.summary.fields_changed', [
                'count' => count($activity->attribute_changes['attributes'] ?? []),
            ]),
            'owner_changed'  => $this->userArrow($activity),
            'place_changed'  => $this->placeArrow($activity),
            'state_changed'  => $this->stateArrow($activity),
            'note'           => trans('history.event.note') . ': ' . Str::limit(
                (string) ($activity->properties['body'] ?? ''),
                80,
            ),
            default          => (string) $activity->description,
        };
    }

    private function userArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => User::find($id)?->name,
        );
    }

    private function placeArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => Place::find($id)?->name,
        );
    }

    private function stateArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($value) => $value === null ? null : AssetState::from($value)->getLabel(),
        );
    }

    private function arrow(mixed $fromKey, mixed $toKey, callable $resolve): string
    {
        return ($fromKey === null ? '—' : ($resolve($fromKey) ?? '—'))
            . ' → '
            . ($toKey === null ? '—' : ($resolve($toKey) ?? '—'));
    }
}
