<?php

namespace App\Support\History;

use App\Enums\HandoverType;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\Location;
use App\Models\StatusLabel;
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

            return $prefix.$body;
        }

        return $body;
    }

    private function bodyFor(Activity $activity): string
    {
        return match ($activity->description) {
            'created' => trans('history.summary.created'),
            'deleted' => trans('history.summary.deleted'),
            'updated' => trans('history.summary.fields_changed', [
                'count' => count($activity->attribute_changes['attributes'] ?? []),
            ]),
            'assignee_changed' => $this->arrow(
                $activity->properties['from'] ?? null,
                $activity->properties['to'] ?? null,
                fn (array $a) => Asset::assigneeNameFor($a['type'] ?? null, $a['id'] ?? null),
            ),
            // owner_changed: entries from before the owner became the assignee.
            'owner_changed' => $this->ownerArrow($activity),
            // place_changed: entries written before places became locations (same ids).
            'location_changed', 'place_changed' => $this->locationArrow($activity),
            'status_changed' => $this->arrow(
                $activity->properties['from'] ?? null,
                $activity->properties['to'] ?? null,
                fn ($id) => StatusLabel::withTrashed()->find($id)?->name,
            ),
            // Entries from before status labels replaced the fixed states hold
            // the old state value, which is the label's system_key.
            'state_changed' => $this->stateArrow($activity),
            'note' => trans('history.event.note').': '.Str::limit(
                (string) ($activity->properties['body'] ?? ''),
                80,
            ),
            'handover_completed' => trans('handover.history.summary.handover_completed', [
                'type' => HandoverType::tryFrom((string) ($activity->properties['type'] ?? ''))?->getLabel() ?? '',
                'recipient' => (string) ($activity->properties['recipient_name'] ?? ''),
            ]),
            default => (string) $activity->description,
        };
    }

    private function ownerArrow(Activity $activity): string
    {
        // Holds user ids: the owner was always a user.
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => User::find($id)?->name,
        );
    }

    private function locationArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => Location::withTrashed()->find($id)?->name,
        );
    }

    private function stateArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($value) => StatusLabel::withTrashed()->where('system_key', $value)->value('name') ?? $value,
        );
    }

    private function arrow(mixed $fromKey, mixed $toKey, callable $resolve): string
    {
        return ($fromKey === null ? '—' : ($resolve($fromKey) ?? '—'))
            .' → '
            .($toKey === null ? '—' : ($resolve($toKey) ?? '—'));
    }
}
