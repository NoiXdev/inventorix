<?php

namespace Tests\Feature\Attachments;

use App\Enums\AttachmentCategory;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_type_maps_mime_to_kind(): void
    {
        $this->assertSame('image', Attachment::detectType('image/png'));
        $this->assertSame('video', Attachment::detectType('video/mp4'));
        $this->assertSame('document', Attachment::detectType('application/pdf'));
        $this->assertSame('document', Attachment::detectType('text/plain'));
    }

    public function test_it_persists_and_casts_category(): void
    {
        $asset = Asset::factory()->create();

        $attachment = Attachment::factory()->create([
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->getKey(),
            'category' => AttachmentCategory::RECHNUNG,
        ]);

        $attachment->refresh();

        $this->assertInstanceOf(AttachmentCategory::class, $attachment->category);
        $this->assertSame(AttachmentCategory::RECHNUNG, $attachment->category);
        $this->assertTrue($attachment->attachable->is($asset));
        $this->assertSame('Rechnung', AttachmentCategory::RECHNUNG->getLabel());
    }
}
