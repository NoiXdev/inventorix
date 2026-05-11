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

    public function test_lend_sets_state_lend_and_owner_to_recipient(): void
    {
        $recipient = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

        $handover = $this->dispatch(HandoverType::LEND, $recipient, $asset);

        $asset->refresh();
        $this->assertSame(AssetState::LEND, $asset->state);
        $this->assertSame($recipient->id, $asset->owner_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'state_to' => AssetState::LEND->value,
            'owner_to_id' => $recipient->id,
        ]);
    }

    public function test_return_clears_owner_and_moves_to_storage(): void
    {
        $previousOwner = User::factory()->create();
        $asset = Asset::factory()->create([
            'state' => AssetState::IN_USE->value,
            'owner_id' => $previousOwner->id,
        ]);

        $handover = $this->dispatch(HandoverType::RETURN_, $previousOwner, $asset);

        $asset->refresh();
        $this->assertSame(AssetState::STORAGE, $asset->state);
        $this->assertNull($asset->owner_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'state_from' => AssetState::IN_USE->value,
            'state_to' => AssetState::STORAGE->value,
            'owner_from_id' => $previousOwner->id,
            'owner_to_id' => null,
        ]);
    }

    public function test_return_defect_clears_owner_and_moves_to_need_repair(): void
    {
        $previousOwner = User::factory()->create();
        $asset = Asset::factory()->create([
            'state' => AssetState::IN_USE->value,
            'owner_id' => $previousOwner->id,
        ]);

        $handover = $this->dispatch(HandoverType::RETURN_DEFECT, $previousOwner, $asset);

        $asset->refresh();
        $this->assertSame(AssetState::NEED_REPAIR, $asset->state);
        $this->assertNull($asset->owner_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'state_to' => AssetState::NEED_REPAIR->value,
        ]);
    }

    private function dispatch(HandoverType $type, User $recipient, Asset $asset): \App\Models\Handover
    {
        $manager = User::factory()->create();

        $data = new HandoverData(
            type: $type,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        return app(HandoverService::class)->commit($data);
    }

    private function onePixelPng(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
}
