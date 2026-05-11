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
}
