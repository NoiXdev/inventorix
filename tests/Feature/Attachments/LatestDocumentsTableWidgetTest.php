<?php

namespace Tests\Feature\Attachments;

use App\Filament\App\Widgets\LatestDocumentsTableWidget;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LatestDocumentsTableWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_documents_newest_first(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        $newest = Attachment::factory()->create(['type' => 'document', 'created_at' => now()]);
        $older = Attachment::factory()->create(['type' => 'document', 'created_at' => now()->subDay()]);
        $image = Attachment::factory()->create(['type' => 'image']);

        Livewire::test(LatestDocumentsTableWidget::class)
            ->assertCanSeeTableRecords([$newest, $older])
            ->assertCanNotSeeTableRecords([$image])
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('category')
            ->assertCanRenderTableColumn('attachable_type')
            ->assertCanRenderTableColumn('created_at')
            ->assertTableActionExists('open');
    }

    public function test_the_open_action_links_to_the_inline_attachment_route(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        $document = Attachment::factory()->create(['type' => 'document']);

        Livewire::test(LatestDocumentsTableWidget::class)
            ->assertTableActionHasUrl('open', route('attachments.open', $document), record: $document);
    }
}
