<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_store_creates_open_incident_linked_to_asset(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", [
            'title' => 'Screen cracked', 'open_date' => '2025-01-10', 'notes' => 'dropped',
        ])->assertRedirect();

        $incident = Incident::where('title', 'Screen cracked')->first();
        $this->assertNotNull($incident);
        $this->assertSame($asset->id, $incident->asset_id);
        $this->assertNull($incident->closed_date);
    }

    public function test_store_validates_required_title_and_open_date(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", ['title' => '', 'open_date' => ''])
            ->assertSessionHasErrors(['title', 'open_date']);
    }

    public function test_store_rejects_closed_before_open(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", [
            'title' => 'x', 'open_date' => '2025-02-01', 'closed_date' => '2025-01-01',
        ])->assertSessionHasErrors('closed_date');
    }

    public function test_update_changes_fields(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id, 'title' => 'Old']);
        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}/incidents/{$incident->id}", [
            'title' => 'New', 'open_date' => '2025-01-10',
        ])->assertRedirect();
        $this->assertSame('New', $incident->fresh()->title);
    }

    public function test_close_sets_closed_date_and_reopen_clears_it(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => null]);

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents/{$incident->id}/close")->assertRedirect();
        $this->assertNotNull($incident->fresh()->closed_date);

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents/{$incident->id}/reopen")->assertRedirect();
        $this->assertNull($incident->fresh()->closed_date);
    }

    public function test_destroy_deletes_incident(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id]);
        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}/incidents/{$incident->id}")->assertRedirect();
        $this->assertNull(Incident::find($incident->id));
    }

    public function test_mutations_reject_incident_from_other_asset(): void
    {
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $assetB->id]);
        $actor = $this->actor();

        $this->actingAs($actor)->delete("/app/assets/{$assetA->id}/incidents/{$incident->id}")->assertForbidden();
        $this->actingAs($actor)->post("/app/assets/{$assetA->id}/incidents/{$incident->id}/close")->assertForbidden();
        $this->assertNotNull(Incident::find($incident->id));
        $this->assertNull($incident->fresh()->closed_date);
    }

    public function test_requires_auth(): void
    {
        $asset = Asset::factory()->create();
        $this->post("/app/assets/{$asset->id}/incidents", [])->assertRedirect();
    }
}
