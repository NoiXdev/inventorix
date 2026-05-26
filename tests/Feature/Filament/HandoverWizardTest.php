<?php

namespace Tests\Feature\Filament;

use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Filament\App\Resources\Handovers\Pages\ListHandovers;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_wizard_flow_creates_a_handover_and_updates_asset(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['login_enabled' => true]);
        $recipient = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($manager);

        $asset = Asset::factory()->create([
            'state' => AssetState::STORAGE->value,
            'owner_id' => null,
        ]);

        Livewire::test(ListHandovers::class)
            ->callAction('new_handover', data: [
                'type' => HandoverType::ISSUE->value,
                'asset_ids' => [$asset->id],
                'recipient_kind' => RecipientKind::INTERNAL->value,
                'recipient_user_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'recipient_email' => $recipient->email,
                'accessories' => 'charger',
                'condition_notes' => null,
                'terms_text' => 'Snapshot',
                'signature_png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            ])
            ->assertHasNoActionErrors();

        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame($recipient->id, $asset->owner_id);
        $this->assertSame(1, Handover::count());
    }
}
