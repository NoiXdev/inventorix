<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\Assets\RelationManagers\HistoryRelationManager;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_relation_manager_lists_asset_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $asset->update(['serial_number' => 'SN-NEW']);

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertCanSeeTableRecords(
                \Spatie\Activitylog\Models\Activity::query()
                    ->where('subject_id', $asset->id)
                    ->get()
            );
    }

    public function test_history_includes_incident_events_for_this_asset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $incident = \App\Models\Incident::factory()->create(['asset_id' => $asset->id]);

        $otherAsset = Asset::factory()->create();
        $otherIncident = \App\Models\Incident::factory()->create(['asset_id' => $otherAsset->id]);

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertCanSeeTableRecords(
                \Spatie\Activitylog\Models\Activity::query()
                    ->where(function ($q) use ($asset, $incident) {
                        $q->where(fn ($q) => $q->where('subject_type', Asset::class)->where('subject_id', $asset->id))
                          ->orWhere(fn ($q) => $q->where('subject_type', \App\Models\Incident::class)->where('subject_id', (string) $incident->id));
                    })->get()
            )
            ->assertCanNotSeeTableRecords(
                \Spatie\Activitylog\Models\Activity::query()
                    ->where('subject_type', \App\Models\Incident::class)
                    ->where('subject_id', (string) $otherIncident->id)
                    ->get()
            );
    }

    public function test_generic_updated_row_is_hidden_when_a_semantic_row_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $newOwner = User::factory()->create();
        $asset->update(['owner_id' => $newOwner->id]);

        $rows = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_id', $asset->id)
            ->get();

        $genericUpdated = $rows->firstWhere('description', 'updated');
        $semantic = $rows->firstWhere('description', 'owner_changed');

        $this->assertNotNull($genericUpdated, 'generic updated row should exist in DB');
        $this->assertNotNull($semantic, 'semantic row should exist in DB');

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertCanSeeTableRecords(collect([$semantic]))
            ->assertCanNotSeeTableRecords(collect([$genericUpdated]));
    }

    public function test_generic_updated_row_is_shown_when_no_semantic_row_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $asset->update(['serial_number' => 'SN-NEW']);

        $genericUpdated = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->first();

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertCanSeeTableRecords(collect([$genericUpdated]));
    }

    public function test_add_note_action_writes_a_note_activity_against_the_asset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->callAction('add_note', data: ['body' => 'Found dent on lid'])
            ->assertHasNoActionErrors();

        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->where('description', 'note')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Found dent on lid', $activity->properties['body']);
        $this->assertSame((string) $user->id, (string) $activity->causer_id);
    }

    public function test_add_note_action_requires_a_body(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->callAction('add_note', data: ['body' => ''])
            ->assertHasActionErrors(['body']);
    }

    public function test_event_filter_narrows_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $newOwner = User::factory()->create();
        $asset->update(['owner_id' => $newOwner->id, 'serial_number' => 'X']);

        $ownerChanged = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_id', $asset->id)->where('description', 'owner_changed')->first();

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->filterTable('event_kind', ['owner_changed'])
            ->assertCanSeeTableRecords(collect([$ownerChanged]));
    }

    public function test_former_user_causer_renders_as_system_former_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        $user->delete();

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertSeeText(trans('history.causer.former_user'));
    }
}
