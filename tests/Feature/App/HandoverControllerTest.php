<?php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Jobs\GenerateHandoverPdf;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandoverControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_store_issue_creates_handover_transitions_assets_and_dispatches_pdf(): void
    {
        Queue::fake();
        $actor = $this->actor();
        $recipient = Person::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value, 'owner_id' => null]);

        $this->actingAs($actor)->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'internal', 'recipient_person_id' => $recipient->id,
            'recipient_name' => $recipient->name, 'recipient_email' => 'r@example.test',
            'asset_ids' => [$asset->id], 'terms_text' => 'Terms apply.', 'signature_png' => self::PNG,
        ])->assertRedirect();

        $handover = Handover::first();
        $this->assertNotNull($handover);
        $this->assertSame($actor->id, $handover->created_by);
        $this->assertNotNull($handover->signed_at);
        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);     // issue -> in-use
        $this->assertSame($recipient->id, $asset->owner_id);      // issue assigns recipient as owner
        $this->assertTrue($handover->assets()->where('assets.id', $asset->id)->exists());
        $pivot = $handover->assets()->first()->pivot;
        $this->assertSame('new', $pivot->state_from);
        $this->assertSame('in-use', $pivot->state_to);
        Queue::assertPushed(GenerateHandoverPdf::class);
    }

    public function test_store_return_moves_asset_to_storage(): void
    {
        Queue::fake();
        $asset = Asset::factory()->create(['state' => AssetState::IN_USE->value]);

        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'return', 'recipient_kind' => 'external',
            'recipient_name' => 'Ext Person', 'recipient_email' => null,
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertRedirect();

        $this->assertSame(AssetState::STORAGE, $asset->fresh()->state);
    }

    public function test_store_rejects_asset_not_in_allowed_state(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::DEFECT->value]); // not allowed for issue

        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'external', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertSessionHasErrors('asset_ids');

        $this->assertSame(0, Handover::count());
    }

    public function test_store_requires_recipient_user_when_internal(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'internal', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertSessionHasErrors('recipient_person_id');
    }

    public function test_store_requires_signature(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'external', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => '',
        ])->assertSessionHasErrors('signature_png');
    }

    public function test_store_rejects_unreadable_signature(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);

        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'external', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => 'not-a-real-png-payload',
        ])->assertSessionHasErrors('signature_png');

        $this->assertSame(0, Handover::count());
    }

    public function test_index_lists_handovers_with_counts(): void
    {
        $h = Handover::factory()->create();
        $h->assets()->attach(Asset::factory()->create()->id, [
            'id' => (string) Str::uuid(), 'state_from' => 'new', 'state_to' => 'in-use',
            'owner_from_id' => null, 'owner_to_id' => null,
        ]);

        $this->actingAs($this->actor())->get('/app/handovers')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('handovers/index')
                ->has('handovers.data', 1, fn (Assert $r) => $r
                    ->has('id')->has('type_label')->has('recipient_name')->has('assets_count')
                    ->where('pdf_ready', false)->etc()));
    }

    public function test_show_returns_detail(): void
    {
        $h = Handover::factory()->create();

        $this->actingAs($this->actor())->get("/app/handovers/{$h->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('handovers/show')
                ->where('handover.id', $h->id)->has('assets'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/handovers')->assertRedirect();
    }
}
