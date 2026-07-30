<?php

namespace Tests\Feature\App\Reports;

use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use App\Reports\AssetsPerEmployeeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetListReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_guarantee_status_filters_by_status(): void
    {
        Asset::factory()->create(['guarantee_end' => now()->subDay()->format('Y-m-d')]);   // expired
        Asset::factory()->create(['guarantee_end' => now()->addDays(400)->format('Y-m-d')]); // valid

        $this->actingAs($this->actor())
            ->get('/app/reports/guarantee_status?filters[status][]=expired')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_asset_aging_min_age_cutoff_and_default(): void
    {
        Asset::factory()->create(['buy_date' => now()->subYears(5)->format('Y-m-d')]); // old
        Asset::factory()->create(['buy_date' => now()->subMonths(6)->format('Y-m-d')]); // new

        // default min_age_years = 3 → only the 5y asset
        $this->actingAs($this->actor())->get('/app/reports/asset_aging')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());

        // min_age_years = 0 → both
        $this->actingAs($this->actor())->get('/app/reports/asset_aging?filters[min_age_years]=0')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 2)->etc());
    }

    public function test_assets_per_employee_pdf_groups_by_owner(): void
    {
        $a = Person::factory()->create(['name' => 'Alice']);
        Asset::factory()->count(2)->create(['owner_id' => $a->id]);
        Asset::factory()->create(['owner_id' => null]); // no-owner group

        $data = app(AssetsPerEmployeeReport::class)->setFilters([])->pdfData();
        $this->assertArrayHasKey('groups', $data);
        $labels = array_map(fn (array $g): string => $g['employee'], $data['groups']);
        // no-owner group is pushed last
        $this->assertSame(__('evaluation.reports.assets_per_employee.pdf.no_owner'), end($labels));
    }

    public function test_assets_per_employee_filters_employees(): void
    {
        $a = Person::factory()->create();
        Asset::factory()->create(['owner_id' => $a->id]);
        Asset::factory()->create(); // different owner

        $this->actingAs($this->actor())
            ->get('/app/reports/assets_per_employee?filters[employees][]='.$a->id)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }
}
