<?php

namespace Tests\Feature\Attachments;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentOpenRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_streams_the_attachment_inline_for_authenticated_users(): void
    {
        Storage::fake();
        Storage::disk()->put('attachments/doc.pdf', 'pdf-contents');

        $attachment = Attachment::factory()->create(['path' => 'attachments/doc.pdf']);

        $response = $this->actingAs(User::factory()->create(['login_enabled' => true]))
            ->get(route('attachments.open', $attachment))
            ->assertOk();

        $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
    }

    public function test_it_returns_404_when_the_file_is_missing(): void
    {
        Storage::fake();

        $attachment = Attachment::factory()->create(['path' => 'attachments/missing.pdf']);

        $this->actingAs(User::factory()->create(['login_enabled' => true]))
            ->get(route('attachments.open', $attachment))
            ->assertNotFound();
    }

    public function test_it_requires_authentication(): void
    {
        $attachment = Attachment::factory()->create();

        $this->get(route('attachments.open', $attachment))
            ->assertRedirect();
    }
}
