<?php

namespace Tests\Feature\App\Reports;

use App\Models\Asset;
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
        return User::factory()->superuser()->create(['login_enabled' => true]);
    }

    public function test_guarantee_status_filters_by_status(): void
    {
        Asset::factory()->warrantyEndingOn(now()->subDay()->format('Y-m-d'))->create();   // expired
        Asset::factory()->warrantyEndingOn(now()->addDays(400)->format('Y-m-d'))->create(); // valid

        $this->actingAs($this->actor())
            ->get('/app/reports/guarantee_status?filters[status][]=expired')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_asset_aging_min_age_cutoff_and_default(): void
    {
        Asset::factory()->create(['purchase_date' => now()->subYears(5)->format('Y-m-d')]); // old
        Asset::factory()->create(['purchase_date' => now()->subMonths(6)->format('Y-m-d')]); // new

        // default min_age_years = 3 → only the 5y asset
        $this->actingAs($this->actor())->get('/app/reports/asset_aging')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());

        // min_age_years = 0 → both
        $this->actingAs($this->actor())->get('/app/reports/asset_aging?filters[min_age_years]=0')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 2)->etc());
    }

    public function test_assets_per_employee_pdf_groups_by_owner(): void
    {
        $a = User::factory()->withoutLogin()->create(['name' => 'Alice']);
        Asset::factory()->count(2)->create(['assignee_type' => User::class, 'assignee_id' => $a->id]);
        Asset::factory()->create(['assignee_type' => null, 'assignee_id' => null]); // no-owner group

        $data = app(AssetsPerEmployeeReport::class)->setFilters([])->pdfData();
        $this->assertArrayHasKey('groups', $data);
        $labels = array_map(fn (array $g): string => $g['employee'], $data['groups']);
        // no-owner group is pushed last
        $this->assertSame(__('evaluation.reports.assets_per_employee.pdf.no_owner'), end($labels));
    }

    public function test_assets_per_employee_filters_employees(): void
    {
        $a = User::factory()->withoutLogin()->create();
        Asset::factory()->create(['assignee_type' => User::class, 'assignee_id' => $a->id]);
        Asset::factory()->create(); // different owner

        $this->actingAs($this->actor())
            ->get('/app/reports/assets_per_employee?filters[employees][]='.$a->id)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }
}
