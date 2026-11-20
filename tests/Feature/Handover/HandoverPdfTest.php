<?php

namespace Tests\Feature\Handover;

use App\DataObjects\HandoverData;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_commit_dispatches_pdf_job_and_writes_pdf_file(): void
    {
        Storage::fake('local');
        Mail::fake();

        $recipient = User::factory()->create();
        $manager = User::factory()->create();
        $asset = Asset::factory()->create([
            'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id,
            'serial' => 'SN-TEST-12345',
        ]);

        $data = new HandoverData(
            type: HandoverType::ISSUE,
            recipientKind: RecipientKind::INTERNAL,
            recipientUserId: $recipient->id,
            recipientName: fake()->name(),
            recipientEmail: null,  // skip mail branch for this task; Task 14 covers email
            assetIds: [$asset->id],
            accessories: 'charger',
            conditionNotes: null,
            termsText: 'Terms-XYZ-snapshot',
            signaturePngBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            signatureIp: '127.0.0.1',
            signatureUserAgent: 'phpunit',
            createdById: $manager->id,
        );

        $handover = app(HandoverService::class)->commit($data);

        // QUEUE_CONNECTION=sync in phpunit.xml — job ran inline.
        $handover->refresh();
        $this->assertNotNull($handover->pdf_path);
        Storage::disk('local')->assertExists($handover->pdf_path);

        $bytes = Storage::disk('local')->get($handover->pdf_path);
        $this->assertSame('%PDF', substr($bytes, 0, 4));
    }
}
