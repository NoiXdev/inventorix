<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Enums\SystemStatus;
use App\Exceptions\HandoverStateConflictException;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
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
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create([
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'assignee_type' => null, 'assignee_id' => null,
        ]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
        $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
        $this->assertSame($recipient->id, $asset->assignee_id);

        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $asset->id,
            'status_from_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'status_to_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'owner_to_id' => $recipient->id,
        ]);

        Storage::disk('local')->assertExists($handover->signature_path);
    }

    public function test_lend_sets_state_lend_and_owner_to_recipient(): void
    {
        $recipient = User::factory()->create();
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);

        $handover = $this->dispatch(HandoverType::LEND, $recipient, $asset);

        $asset->refresh();
        $this->assertSame(SystemStatus::LEND, $asset->status->system_key);
        $this->assertSame($recipient->id, $asset->assignee_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'status_to_id' => StatusLabel::system(SystemStatus::LEND)->id,
            'owner_to_id' => $recipient->id,
        ]);
    }

    public function test_return_clears_owner_and_moves_to_storage(): void
    {
        $previousOwner = User::factory()->withoutLogin()->create();
        $asset = Asset::factory()->create([
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'assignee_type' => User::class, 'assignee_id' => $previousOwner->id,
        ]);

        // RETURN_ never assigns the recipient as owner, so the recipient here
        // doesn't have to be $previousOwner — it just has to resolve in `users`.
        $handover = $this->dispatch(HandoverType::RETURN_, User::factory()->create(), $asset);

        $asset->refresh();
        $this->assertSame(SystemStatus::STORAGE, $asset->status->system_key);
        $this->assertNull($asset->assignee_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'status_from_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'status_to_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'owner_from_id' => $previousOwner->id,
            'owner_to_id' => null,
        ]);
    }

    public function test_return_defect_clears_owner_and_moves_to_need_repair(): void
    {
        $previousOwner = User::factory()->withoutLogin()->create();
        $asset = Asset::factory()->create([
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
            'assignee_type' => User::class, 'assignee_id' => $previousOwner->id,
        ]);

        // RETURN_DEFECT never assigns the recipient as owner, so the recipient
        // here doesn't have to be $previousOwner — it just has to resolve in `users`.
        $handover = $this->dispatch(HandoverType::RETURN_DEFECT, User::factory()->create(), $asset);

        $asset->refresh();
        $this->assertSame(SystemStatus::NEED_REPAIR, $asset->status->system_key);
        $this->assertNull($asset->assignee_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'status_to_id' => StatusLabel::system(SystemStatus::NEED_REPAIR)->id,
        ]);
    }

    public function test_state_conflict_throws_and_leaves_no_db_rows(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,                   // requires NEW or STORAGE
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
        } catch (HandoverStateConflictException $e) {
            $this->assertSame([$asset->id], $e->assetIds);
        }

        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('handover_asset', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('handovers'));
    }

    public function test_unknown_asset_id_throws_state_conflict(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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

        $this->expectException(HandoverStateConflictException::class);
        app(HandoverService::class)->commit($data);
    }

    public function test_throwing_inside_transaction_deletes_signature_file(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);

        // Force the inner Handover insert to fail via a non-existent created_by UUID;
        // SQLite enforces the FK because Laravel enables it on connection open.
        $bogusManagerId = (string) Str::uuid();

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
        $asset = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);

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
        $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
        $this->assertNull($asset->assignee_id);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $asset->id,
            'owner_to_id' => null,
        ]);
    }

    public function test_bulk_handover_attaches_each_asset_with_its_own_snapshots(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $a = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);
        $b = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
            'status_from_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'owner_from_id' => null,
        ]);
        $this->assertDatabaseHas('handover_asset', [
            'handover_id' => $handover->id,
            'asset_id' => $b->id,
            'status_from_id' => StatusLabel::system(SystemStatus::NEW)->id,
            'owner_to_id' => $recipient->id,
        ]);

        $a->refresh();
        $b->refresh();
        $this->assertSame(SystemStatus::IN_USE, $a->status->system_key);
        $this->assertSame(SystemStatus::IN_USE, $b->status->system_key);
    }

    public function test_bulk_with_one_invalid_state_rolls_everything_back(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $valid = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);
        $invalid = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);  // not allowed for ISSUE

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
        } catch (HandoverStateConflictException $e) {
            $this->assertSame([$invalid->id], $e->assetIds);
        }

        $valid->refresh();
        $this->assertSame(SystemStatus::STORAGE, $valid->status->system_key, 'Valid asset must not be partially handed over.');
        $this->assertDatabaseCount('handovers', 0);
    }

    public function test_commit_writes_handover_completed_activity_per_asset(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $this->actingAs($manager);
        $a = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);
        $b = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id, 'assignee_type' => null, 'assignee_id' => null]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
            $activity = Activity::query()
                ->where('subject_type', Asset::class)
                ->where('subject_id', $assetId)
                ->where('description', 'handover_completed')
                ->first();

            $this->assertNotNull($activity, "No handover_completed activity for asset {$assetId}");
            $this->assertSame('asset', $activity->log_name);
            $this->assertSame($handover->id, $activity->properties['handover_id']);
            $this->assertSame(HandoverType::ISSUE->value, $activity->properties['type']);
            $this->assertSame($recipientName, $activity->properties['recipient_name']);
        }
    }

    public function test_invalid_base64_payload_throws(): void
    {
        $recipient = User::factory()->create();
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $asset = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $asset = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: base64_encode('GIF87a'.str_repeat('x', 100)),
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
        $recipientName = fake()->name();
        $manager = User::factory()->create();
        $asset = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);

        $tooBig = "\x89PNG\r\n\x1a\n".str_repeat('x', config('handover.signature.max_bytes') + 1);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipientName,
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

    public function test_issue_checks_out_and_return_checks_in(): void
    {
        $recipient = User::factory()->create();
        $asset = Asset::factory()->unassigned()->create([
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'expected_checkin' => now()->addWeek(),
        ]);

        $this->dispatch(HandoverType::ISSUE, $recipient, $asset);
        $asset->refresh();
        $this->assertSame(1, $asset->checkout_counter);
        $this->assertNotNull($asset->last_checkout);
        $this->assertSame(User::class, $asset->assignee_type);

        $this->dispatch(HandoverType::RETURN_, $recipient, $asset);
        $asset->refresh();
        $this->assertSame(1, $asset->checkin_counter);
        $this->assertNotNull($asset->last_checkin);
        $this->assertNull($asset->expected_checkin);
        $this->assertNull($asset->assignee_type);
    }

    public function test_an_asset_assigned_to_a_location_has_no_previous_owner(): void
    {
        $asset = Asset::factory()->assignedTo(Location::factory()->create())->create([
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id,
        ]);

        $handover = $this->dispatch(HandoverType::RETURN_, User::factory()->create(), $asset);

        $this->assertNull($handover->assets()->sole()->pivot->owner_from_id);
        $this->assertNull($asset->fresh()->assignee_id);
    }

    public function test_issuing_an_asset_that_is_checked_out_is_a_conflict(): void
    {
        $asset = Asset::factory()->assignedTo(Location::factory()->create())->create([
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
        ]);

        try {
            $this->dispatch(HandoverType::ISSUE, User::factory()->create(), $asset);
            $this->fail('Expected conflict');
        } catch (HandoverStateConflictException $e) {
            $this->assertSame([$asset->id], $e->assetIds);
        }
    }

    public function test_external_issue_counts_a_checkout_without_assignee(): void
    {
        $asset = Asset::factory()->unassigned()->create(['status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);

        app(HandoverService::class)->commit(new HandoverData(
            type: HandoverType::LEND,
            recipientKind: RecipientKind::EXTERNAL,
            recipientUserId: null,
            recipientName: 'External Person',
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms snapshot',
            signaturePngBase64: $this->onePixelPng(),
            signatureIp: null,
            signatureUserAgent: null,
            createdById: User::factory()->create()->id,
        ));

        $asset->refresh();
        $this->assertSame(SystemStatus::LEND, $asset->status->system_key);
        $this->assertNull($asset->assignee_id);
        $this->assertSame(1, $asset->checkout_counter);
        $this->assertNotNull($asset->last_checkout);
    }

    private function dispatch(HandoverType $type, User $recipient, Asset $asset): Handover
    {
        $manager = User::factory()->create();

        $data = new HandoverData(
            type: $type,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: fake()->name(),
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
