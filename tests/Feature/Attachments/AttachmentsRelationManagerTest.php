<?php

namespace Tests\Feature\Attachments;

use App\Enums\AttachmentCategory;
use App\Filament\App\Resources\Assets\RelationManagers\AttachmentsRelationManager;
use App\Filament\App\Resources\Assets\Pages\EditAsset;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_action_creates_attachments_with_metadata(): void
    {
        Storage::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        Livewire::test(AttachmentsRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])
            ->callTableAction('upload', data: [
                'files' => [UploadedFile::fake()->image('photo.jpg')],
                'category' => AttachmentCategory::FOTO->value,
                'note' => 'Frontansicht',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $asset->attachments()->count());

        $attachment = $asset->attachments()->first();
        $this->assertSame('image', $attachment->type);
        $this->assertSame(AttachmentCategory::FOTO, $attachment->category);
        $this->assertSame('photo.jpg', $attachment->original_name);
        $this->assertSame('photo.jpg', $attachment->title); // defaulted from filename
        $this->assertSame($user->getKey(), $attachment->uploaded_by);
        Storage::disk()->assertExists($attachment->path);
    }
}
