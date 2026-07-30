<?php

namespace Tests\Feature\App\Reports;

use App\Enums\HandoverType;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoryReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_registry_exposes_all_eight(): void
    {
        $this->actingAs($this->actor())->get('/app/reports')
            ->assertInertia(fn (Assert $p) => $p->has('reports', 8)->etc());
    }

    public function test_incident_history_status_filter(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => null]);
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => now()]);

        $this->actingAs($this->actor())->get('/app/reports/incident_history?filters[status]=open')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_incident_history_date_range(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->create(['asset_id' => $asset->id, 'open_date' => now()->subDays(2)]);
        Incident::factory()->create(['asset_id' => $asset->id, 'open_date' => now()->subDays(30)]);

        $this->actingAs($this->actor())
            ->get('/app/reports/incident_history?filters[from]='.now()->subDays(5)->format('Y-m-d'))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_handover_history_type_filter(): void
    {
        Handover::factory()->create(['type' => HandoverType::ISSUE->value]);
        Handover::factory()->create(['type' => HandoverType::RETURN_->value]);

        $this->actingAs($this->actor())
            ->get('/app/reports/handover_history?filters[type][]='.HandoverType::ISSUE->value)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }
}
