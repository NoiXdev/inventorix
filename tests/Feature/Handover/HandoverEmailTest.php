<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\AssetState;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Mail\HandoverSigned;
use App\Models\Asset;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_sent_with_pdf_attachment_when_recipient_has_email(): void
    {
        Storage::fake('local');
        Mail::fake();

        $recipient = User::factory()->create(['email' => 'alice@example.com']);
        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        app(HandoverService::class)->commit(new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: $recipient->name,
            recipientEmail: 'alice@example.com',
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        ));

        Mail::assertQueued(HandoverSigned::class, function (HandoverSigned $mail): bool {
            return $mail->hasTo('alice@example.com');
        });
    }

    public function test_email_is_skipped_when_recipient_email_is_null(): void
    {
        Storage::fake('local');
        Mail::fake();

        $manager = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::STORAGE->value]);

        app(HandoverService::class)->commit(new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::EXTERNAL,
            recipientUserId: null,
            recipientName: 'Walk-in',
            recipientEmail: null,
            assetIds: [$asset->id],
            accessories: null,
            conditionNotes: null,
            termsText: 'Terms',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: null,
            signatureUserAgent: null,
            createdById: $manager->id,
        ));

        Mail::assertNothingQueued();
    }
}
