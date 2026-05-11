<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_issue_sets_state_in_use_and_owner_to_recipient(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create([
            'state' => AssetState::STORAGE->value,
            'owner_id' => null,
        ]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$asset->id],
            accessories: 'Ladegerät',
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        $this->assertDatabaseHas('handovers', [
            'id' => $handover->id,
            'type' => HandoverType::ISSUE->value,
            'recipient_user_id' => $recipient->id,
            'created_by' => $manager->id,
        ]);

        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame($recipient->id, $asset->owner_id);

        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $asset->id,
            'state_from' => AssetState::STORAGE->value,
            'state_to' => AssetState::IN_USE->value,
            'owner_to_id' => $recipient->id,
        ]);

        Storage::disk('local')->assertExists($handover->signature_path);
    }

    private function onePixelPng(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
}
