<?php

namespace Tests\Feature\Attachments;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetAttachmentCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deleting_asset_removes_all_attachment_files_and_rows(): void
    {
        Storage::fake();

        $asset = Asset::factory()->create();

        $path1 = UploadedFile::fake()->create('invoice.pdf', 10)->store('attachments');
        $path2 = UploadedFile::fake()->create('photo.jpg', 20)->store('attachments');

        Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'path' => $path1,
            'original_name' => 'invoice.pdf',
        ]);

        Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'path' => $path2,
            'original_name' => 'photo.jpg',
        ]);

        Storage::disk()->assertExists($path1);
        Storage::disk()->assertExists($path2);
        $this->assertSame(2, Attachment::count());

        $asset->forceDelete();

        Storage::disk()->assertMissing($path1);
        Storage::disk()->assertMissing($path2);
        $this->assertSame(0, Attachment::count());
    }

    public function test_soft_deleting_asset_keeps_its_attachments(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $path = UploadedFile::fake()->create('invoice.pdf', 10)->store('attachments');
        Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'path' => $path,
            'original_name' => 'invoice.pdf',
        ]);

        $asset->delete();

        Storage::disk()->assertExists($path);
        $this->assertSame(1, Attachment::count());
    }
}
