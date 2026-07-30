<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_upload_creates_attachment_rows_and_stores_files(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $actor = $this->actor();

        $this->actingAs($actor)->post("/app/assets/{$asset->id}/attachments", [
            'files' => [
                UploadedFile::fake()->image('photo.jpg'),
                UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
            ],
            'category' => 'dokument',
            'title' => 'Docs',
        ])->assertRedirect();

        $this->assertSame(2, $asset->attachments()->count());
        $image = $asset->attachments()->where('original_name', 'photo.jpg')->first();
        $this->assertSame('image', $image->type);
        $this->assertSame($actor->id, $image->uploaded_by);
        Storage::disk()->assertExists($image->path);
    }

    public function test_upload_requires_at_least_one_file(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", ['files' => []])
            ->assertSessionHasErrors('files');
    }

    public function test_upload_rejects_bad_category(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", [
            'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
            'category' => 'bogus',
        ])->assertSessionHasErrors('category');
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", [
            'files' => [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, $asset->attachments()->count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/attachments", [
            'files' => [UploadedFile::fake()->create('big.pdf', 60_000, 'application/pdf')], // ~60MB > 50MB
        ])->assertSessionHasErrors('files.0');
    }

    public function test_delete_removes_row_and_file(): void
    {
        Storage::fake();
        $asset = Asset::factory()->create();
        Storage::disk()->put('attachments/x.pdf', 'data');
        $attachment = $asset->attachments()->create([
            'path' => 'attachments/x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf',
            'size' => 4, 'type' => 'document', 'category' => 'dokument',
        ]);

        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}/attachments/{$attachment->id}")
            ->assertRedirect();

        $this->assertNull(Attachment::find($attachment->id));
        Storage::disk()->assertMissing('attachments/x.pdf');
    }

    public function test_delete_rejects_attachment_from_other_asset(): void
    {
        Storage::fake();
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        $attachment = $assetB->attachments()->create([
            'path' => 'attachments/y.pdf', 'original_name' => 'y.pdf', 'mime_type' => 'application/pdf',
            'size' => 4, 'type' => 'document',
        ]);

        $this->actingAs($this->actor())->delete("/app/assets/{$assetA->id}/attachments/{$attachment->id}")
            ->assertForbidden();
        $this->assertNotNull(Attachment::find($attachment->id));
    }

    public function test_upload_requires_auth(): void
    {
        $asset = Asset::factory()->create();
        $this->post("/app/assets/{$asset->id}/attachments", ['files' => []])->assertRedirect(); // to login
    }
}
