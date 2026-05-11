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

    public function test_state_conflict_throws_and_leaves_no_db_rows(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::IN_USE->value]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,                   // requires NEW or STORAGE
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        try {
            app(HandoverService::class)->commit($data);
            $this->fail('Expected HandoverStateConflictException');
        } catch (\App\Exceptions\HandoverStateConflictException $e) {
            $this->assertSame([$asset->id], $e->assetIds);
        }

        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('handover_asset', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('handovers'));
    }

    public function test_unknown_asset_id_throws_state_conflict(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: ['00000000-0000-0000-0000-000000000000'],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $this->expectException(\App\Exceptions\HandoverStateConflictException::class);
        app(HandoverService::class)->commit($data);
    }

    public function test_throwing_inside_transaction_deletes_signature_file(): void
    {
        $recipient = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

        // Force the inner Handover insert to fail via a non-existent created_by UUID;
        // SQLite enforces the FK because Laravel enables it on connection open.
        $bogusManagerId = (string) \Illuminate\Support\Str::uuid();

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $bogusManagerId,
        );

        $this->expectException(\Throwable::class);

        try {
            app(HandoverService::class)->commit($data);
        } finally {
            $this->assertCount(0, Storage::disk('local')->allFiles('handovers'));
        }
    }

    public function test_external_recipient_leaves_owner_null_but_changes_state(): void
    {
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::EXTERNAL,
            recipientUserId: null,
            recipientName: 'Jane External',
            recipientEmail: 'jane@example.com',
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);
        $asset->refresh();

        $this->assertNull($handover->recipient_user_id);
        $this->assertSame('Jane External', $handover->recipient_name);
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertNull($asset->owner_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $asset->id,
            'owner_to_id' => null,
        ]);
    }

    public function test_bulk_handover_attaches_each_asset_with_its_own_snapshots(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $previousOwner = User::factory()->create();

        $a = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);
        $b = Asset::factory()->create(['state' => AssetState::NEW->value, 'owner_id' => $previousOwner->id]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$a->id, $b->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $a->id,
            'state_from' => AssetState::STORAGE->value,
            'owner_from_id' => null,
        ]);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $b->id,
            'state_from' => AssetState::NEW->value,
            'owner_from_id' => $previousOwner->id,
        ]);

        $a->refresh();
        $b->refresh();
        $this->assertSame(AssetState::IN_USE, $a->state);
        $this->assertSame(AssetState::IN_USE, $b->state);
    }

    public function test_bulk_with_one_invalid_state_rolls_everything_back(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $valid = Asset::factory()->create(['state' => AssetState::STORAGE->value]);
        $invalid = Asset::factory()->create(['state' => AssetState::IN_USE->value]);  // not allowed for ISSUE

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$valid->id, $invalid->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        try {
            app(HandoverService::class)->commit($data);
            $this->fail('Expected conflict');
        } catch (\App\Exceptions\HandoverStateConflictException $e) {
            $this->assertSame([$invalid->id], $e->assetIds);
        }

        $valid->refresh();
        $this->assertSame(AssetState::STORAGE, $valid->state, 'Valid asset must not be partially handed over.');
        $this->assertDatabaseCount('handovers', 0);
    }

    public function test_commit_writes_handover_completed_activity_per_asset(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $this->actingAs($manager);
        $a = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);
        $b = Asset::factory()->create(['state' => AssetState::STORAGE->value, 'owner_id' => null]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: $recipient->email,
            assetIds: [$a->id, $b->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        foreach ([$a->id, $b->id] as $assetId) {
            $activity = \Spatie\Activitylog\Models\Activity::query()
                ->where('subject_type', Asset::class)
                ->where('subject_id', $assetId)
                ->where('description', 'handover_completed')
                ->first();

            $this->assertNotNull($activity, "No handover_completed activity for asset {$assetId}");
            $this->assertSame('asset', $activity->log_name);
            $this->assertSame($handover->id, $activity->properties['handover_id']);
            $this->assertSame(HandoverType::ISSUE->value, $activity->properties['type']);
            $this->assertSame($recipient->name, $activity->properties['recipient_name']);
        }
    }

    public function test_invalid_base64_payload_throws(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: 'not-base64!!!',
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $this->expectException(\InvalidArgumentException::class);
        app(HandoverService::class)->commit($data);
    }

    public function test_non_png_payload_throws(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: base64_encode('GIF87a' . str_repeat('x', 100)),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $this->expectException(\InvalidArgumentException::class);
        app(HandoverService::class)->commit($data);
    }

    public function test_too_large_signature_throws(): void
    {
        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        $tooBig = "\x89PNG\r\n\x1a\n" . str_repeat('x', config('handover.signature.max_bytes') + 1);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: base64_encode($tooBig),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        );

        $this->expectException(\InvalidArgumentException::class);
        app(HandoverService::class)->commit($data);
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
