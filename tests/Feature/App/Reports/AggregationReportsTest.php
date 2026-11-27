<?php

namespace Tests\Feature\App\Reports;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\StatusLabel;
use App\Models\User;
use App\Reports\AssetValueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AggregationReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superuser()->create(['login_enabled' => true]);
    }

    public function test_state_overview_is_grouped_and_not_paginated(): void
    {
        Asset::factory()->count(2)->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id]);
        Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);

        $this->actingAs($this->actor())->get('/app/reports/state_overview')
            ->assertInertia(fn (Assert $p) => $p->component('reports/show')
                ->has('rows', 2)          // one row per distinct state
                ->missing('pagination')   // non-paginated
                ->etc());
    }

    public function test_asset_value_aggregated_vs_detailed(): void
    {
        $owner = User::factory()->withoutLogin()->create();
        Asset::factory()->count(2)->create(['assignee_type' => User::class, 'assignee_id' => $owner->id, 'purchase_cost' => 100]);

        // aggregated (default): grouped rows, no pagination, no totals
        $this->actingAs($this->actor())->get('/app/reports/asset_value')
            ->assertInertia(fn (Assert $p) => $p->missing('pagination')->missing('totals')->etc());

        // detailed: per-asset rows, paginated, purchase_cost total
        $this->actingAs($this->actor())->get('/app/reports/asset_value?filters[detailed]=1')
            ->assertInertia(fn (Assert $p) => $p->has('pagination')
                // NOTE: not `->where('totals.purchase_cost', 200.0)` — AssertableInertia
                // normalizes props via json_decode(json_encode(...)), and PHP's json_encode
                // collapses whole-number floats (200.0) to ints, so a strict `===` against
                // the float literal fails. Cast explicitly to sidestep that JSON round-trip
                // artifact while keeping the same numeric assertion.
                ->where('totals.purchase_cost', fn ($value): bool => (float) $value === 200.0)->etc());
    }

    public function test_asset_value_pdf_has_grand_total_row(): void
    {
        Asset::factory()->create(['purchase_cost' => 150]);

        $report = app(AssetValueReport::class)->setFilters([]);
        $data = $report->pdfData();
        $lastRow = end($data['rows']);
        // aggregated grand-total row: label in col 0, sum in the total_price column (index 2)
        $this->assertSame(__('evaluation.reports.asset_value.total'), $lastRow[0]);
        $this->assertEqualsWithDelta(150.0, (float) $lastRow[2], 0.001);
    }

    public function test_asset_value_groups_by_the_model_category(): void
    {
        $notebook = Category::factory()->create(['name' => 'Notebook']);
        $model = AssetModel::factory()->create(['category_id' => $notebook->id]);
        Asset::factory()->count(2)->create(['model_id' => $model->id, 'purchase_cost' => 100]);
        Asset::factory()->create(['model_id' => null, 'purchase_cost' => 50]);

        $rows = app(AssetValueReport::class)->setFilters(['group_by' => 'category'])->pdfData()['rows'];
        $byGroup = collect($rows)->mapWithKeys(fn (array $r) => [$r[0] => [(int) $r[1], (float) $r[2]]]);

        $this->assertSame([2, 200.0], $byGroup['Notebook']);
        $this->assertSame([1, 50.0], $byGroup['—']);
    }
}
