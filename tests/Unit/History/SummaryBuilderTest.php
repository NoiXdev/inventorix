<?php

namespace Tests\Unit\History;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\Place;
use App\Models\User;
use App\Support\History\SummaryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SummaryBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function buildActivity(array $overrides): Activity
    {
        return Activity::create(array_merge([
            'log_name' => 'asset',
            'description' => 'updated',
            'subject_type' => Asset::class,
            'subject_id' => (string) Str::uuid(),
            'properties' => [],
        ], $overrides));
    }

    public function test_created_summary(): void
    {
        $activity = $this->buildActivity(['description' => 'created']);
        $this->assertSame(trans('history.summary.created'), (new SummaryBuilder)->forActivity($activity));
    }

    public function test_deleted_summary(): void
    {
        $activity = $this->buildActivity(['description' => 'deleted']);
        $this->assertSame(trans('history.summary.deleted'), (new SummaryBuilder)->forActivity($activity));
    }

    public function test_updated_summary_counts_changed_fields(): void
    {
        $activity = $this->buildActivity([
            'description' => 'updated',
            'attribute_changes' => ['attributes' => ['a' => 1, 'b' => 2, 'c' => 3], 'old' => ['a' => 0, 'b' => 0, 'c' => 0]],
        ]);
        $this->assertSame(
            trans('history.summary.fields_changed', ['count' => 3]),
            (new SummaryBuilder)->forActivity($activity),
        );
    }

    public function test_owner_changed_summary_resolves_names(): void
    {
        $old = User::factory()->create(['name' => 'Anna']);
        $new = User::factory()->create(['name' => 'Lukas']);
        $activity = $this->buildActivity([
            'description' => 'owner_changed',
            'properties' => ['from' => $old->id, 'to' => $new->id],
        ]);
        $this->assertSame('Anna → Lukas', (new SummaryBuilder)->forActivity($activity));
    }

    public function test_owner_changed_summary_handles_null(): void
    {
        $new = User::factory()->create(['name' => 'Lukas']);
        $activity = $this->buildActivity([
            'description' => 'owner_changed',
            'properties' => ['from' => null, 'to' => $new->id],
        ]);
        $this->assertSame('— → Lukas', (new SummaryBuilder)->forActivity($activity));
    }

    public function test_place_changed_summary_resolves_names(): void
    {
        $old = Place::factory()->create(['name' => 'Lager A']);
        $new = Place::factory()->create(['name' => 'Lager B']);
        $activity = $this->buildActivity([
            'description' => 'place_changed',
            'properties' => ['from' => $old->id, 'to' => $new->id],
        ]);
        $this->assertSame('Lager A → Lager B', (new SummaryBuilder)->forActivity($activity));
    }

    public function test_state_changed_summary_uses_enum_labels(): void
    {
        $activity = $this->buildActivity([
            'description' => 'state_changed',
            'properties' => ['from' => AssetState::NEW->value, 'to' => AssetState::IN_USE->value],
        ]);
        $this->assertSame(
            AssetState::NEW->getLabel().' → '.AssetState::IN_USE->getLabel(),
            (new SummaryBuilder)->forActivity($activity),
        );
    }

    public function test_note_summary_truncates(): void
    {
        $long = str_repeat('x', 200);
        $activity = $this->buildActivity([
            'description' => 'note',
            'properties' => ['body' => $long],
        ]);
        $result = (new SummaryBuilder)->forActivity($activity);
        $this->assertStringStartsWith(trans('history.event.note').': ', $result);
        $this->assertLessThanOrEqual(strlen(trans('history.event.note').': ') + 80 + 3 /* ellipsis */, strlen($result));
    }

    public function test_incident_subject_gets_prefix(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id]);
        $activity = $this->buildActivity([
            'log_name' => 'incident',
            'subject_type' => Incident::class,
            'subject_id' => (string) $incident->id,
            'description' => 'created',
        ]);
        $result = (new SummaryBuilder)->forActivity($activity);
        $this->assertStringStartsWith(trans('history.summary.incident_prefix', ['id' => $incident->id]), $result);
    }

    public function test_incident_subject_for_deleted_incident_gets_removed_prefix(): void
    {
        $activity = $this->buildActivity([
            'log_name' => 'incident',
            'subject_type' => Incident::class,
            'subject_id' => '999999', // does not exist
            'description' => 'created',
        ]);
        $result = (new SummaryBuilder)->forActivity($activity);
        $this->assertStringStartsWith(trans('history.summary.incident_removed'), $result);
    }

    public function test_handover_completed_summary_renders_type_and_recipient(): void
    {
        $activity = new Activity;
        $activity->description = 'handover_completed';
        $activity->subject_type = Asset::class;
        $activity->subject_id = (string) Str::uuid();
        $activity->properties = new Collection([
            'type' => 'issue',
            'recipient_name' => 'Alice',
        ]);

        $out = (new SummaryBuilder)->forActivity($activity);
        $this->assertStringContainsString('Alice', $out);
    }
}
