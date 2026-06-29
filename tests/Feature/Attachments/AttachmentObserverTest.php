<?php

namespace Tests\Feature\Attachments;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_attachment_removes_file_and_logs_activity(): void
    {
        Storage::fake();

        $asset = Asset::factory()->create();
        $path = UploadedFile::fake()->create('doc.pdf', 10)->store('attachments');

        Storage::disk()->assertExists($path);

        $attachment = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'path' => $path,
            'original_name' => 'doc.pdf',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'asset',
            'event' => 'attachment_added',
            'subject_id' => $asset->getKey(),
        ]);

        $attachment->delete();

        Storage::disk()->assertMissing($path);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'asset',
            'event' => 'attachment_removed',
            'subject_id' => $asset->getKey(),
        ]);
    }
}
