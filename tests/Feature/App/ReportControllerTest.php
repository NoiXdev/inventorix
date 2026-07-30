<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_reports(): void
    {
        $this->actingAs($this->actor())->get('/app/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('reports/index')
                ->has('reports', fn (Assert $r) => $r->etc())
                ->where('reports.0.key', 'assets_per_employee')
                ->has('reports.0', fn (Assert $r) => $r->has('key')->has('label')->has('description')->has('icon'))
                ->etc());
    }

    public function test_show_returns_columns_and_rows(): void
    {
        Asset::factory()->count(3)->create();

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('reports/show')
                ->where('meta.key', 'inventory_by_location')
                ->has('filterConfig')
                ->has('columns', 6)
                ->has('rows', 3)
                ->has('pagination', fn (Assert $pg) => $pg->where('per_page', 25)->where('total', 3)->etc())
                ->etc());
    }

    public function test_show_places_filter(): void
    {
        $keep = Place::factory()->create();
        $other = Place::factory()->create();
        Asset::factory()->create(['place_id' => $keep->id]);
        Asset::factory()->create(['place_id' => $other->id]);

        $this->actingAs($this->actor())
            ->get('/app/reports/inventory_by_location?filters[places][]='.$keep->id)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_per_page_clamped(): void
    {
        Asset::factory()->count(2)->create();

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location?per_page=999')
            ->assertInertia(fn (Assert $p) => $p->where('pagination.per_page', 25)->etc());

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location?per_page=50')
            ->assertInertia(fn (Assert $p) => $p->where('pagination.per_page', 50)->etc());
    }

    public function test_pdf_streams(): void
    {
        Asset::factory()->create();

        $res = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/pdf');
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($res->headers->get('content-type') ?? ''));
    }

    public function test_export_csv_and_xlsx(): void
    {
        Asset::factory()->create();

        $csv = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', strtolower($csv->headers->get('content-type') ?? ''));

        $xlsx = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', strtolower($xlsx->headers->get('content-type') ?? ''));
    }

    public function test_export_bad_format_rejected(): void
    {
        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=pdf')
            ->assertStatus(422);
    }

    public function test_unknown_report_404(): void
    {
        $this->actingAs($this->actor())->get('/app/reports/nope')->assertNotFound();
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/reports')->assertRedirect();
    }
}
