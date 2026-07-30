<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_stats_and_warranty_buckets(): void
    {
        Asset::factory()->create(['guarantee_end' => now()->subDay()->format('Y-m-d')]);        // expired
        Asset::factory()->create(['guarantee_end' => now()->addDays(10)->format('Y-m-d')]);     // soon_30 + soon_90
        Asset::factory()->create(['guarantee_end' => now()->addDays(60)->format('Y-m-d')]);     // soon_90 only
        Asset::factory()->create(['guarantee_end' => now()->addDays(200)->format('Y-m-d')]);    // neither
        Asset::factory()->create(['guarantee_end' => null]);                                     // ignored

        $this->actingAs($this->actor())->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('dashboard')
                ->where('stats.assets', 5)
                ->where('warranty.expired', 1)
                ->where('warranty.soon_30', 1)
                ->where('warranty.soon_90', 2)
                ->etc());
    }

    public function test_latest_documents_only_documents_newest_first_capped(): void
    {
        Attachment::factory()->count(12)->create(['type' => 'document']);
        Attachment::factory()->create(['type' => 'image', 'original_name' => 'pic.jpg']);

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p
                ->has('latestDocuments', 10) // capped
                ->has('latestDocuments.0', fn (Assert $r) => $r->has('id')->has('title')->has('attached_to')->has('url')->etc())
                ->etc());
    }

    public function test_open_incidents_excludes_closed_and_caps(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->count(12)->create(['asset_id' => $asset->id, 'closed_date' => null]);
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => now()]);

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->has('openIncidents', 10)
                ->has('openIncidents.0', fn (Assert $r) => $r->has('id')->has('title')->has('asset_url')->has('days_open')->etc())
                ->etc());
    }

    public function test_warranty_expiring_ordered_soonest_first(): void
    {
        Asset::factory()->create(['serial_number' => 'LATER', 'guarantee_end' => now()->addDays(50)->format('Y-m-d')]);
        Asset::factory()->create(['serial_number' => 'SOONER', 'guarantee_end' => now()->addDays(5)->format('Y-m-d')]);
        Asset::factory()->create(['guarantee_end' => null]); // excluded

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->has('warrantyExpiring', 2)
                ->where('warrantyExpiring.0.serial', 'SOONER')->etc());
    }

    public function test_requires_auth(): void
    {
        $this->get('/app')->assertRedirect();
    }
}
