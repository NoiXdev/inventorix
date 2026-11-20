<?php

namespace Tests\Feature\App;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Incident;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_export_streams_csv_with_header_and_resolved_row(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $type = Category::factory()->create(['name' => 'Laptop']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id, 'category_id' => $type->id]);
        $asset = Asset::factory()->create([
            'model_id' => $model->id, 'assignee_type' => null, 'assignee_id' => null,
            'status_id' => StatusLabel::system(SystemStatus::IN_USE)->id, 'serial' => 'SN-9', 'purchase_date' => '2024-01-05',
            'name' => 'Ada laptop', 'asset_tag' => 'IT-42', 'warranty_months' => 36, 'order_number' => 'PO-7',
            'supplier_id' => Supplier::factory()->create(['name' => 'Bechtle'])->id,
        ]);
        $asset->syncTags(['a', 'b']);

        $res = $this->actingAs($this->actor())->get('/app/assets/export');
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();

        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertStringContainsString('id,name,asset_tag,status,category,manufacturer,model,serial,location,assigned_user,purchase_date,purchase_cost,supplier,order_number,warranty_months,notes,tags', $lines[0]);
        $this->assertStringContainsString('Laptop', $csv);
        $this->assertStringContainsString('Acme', $csv);
        $this->assertStringContainsString('X1', $csv);
        $this->assertStringContainsString(StatusLabel::system(SystemStatus::IN_USE)->name, $csv);
        $this->assertStringContainsString('2024-01-05', $csv);
        $this->assertStringContainsString('"a, b"', $csv); // tags joined, quoted by fputcsv
        $this->assertStringContainsString('"Ada laptop",IT-42,', $lines[1]);
        $this->assertStringContainsString(',Bechtle,PO-7,36,', $lines[1]);
    }

    public function test_export_respects_status_filter(): void
    {
        Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id, 'serial' => 'KEEP']);
        Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::DEFECT)->id, 'serial' => 'DROP']);

        $csv = $this->actingAs($this->actor())->get('/app/assets/export?filter[status_id]='.StatusLabel::system(SystemStatus::IN_USE)->id)->streamedContent();

        $this->assertStringContainsString('KEEP', $csv);
        $this->assertStringNotContainsString('DROP', $csv);
    }

    public function test_export_requires_auth(): void
    {
        $this->get('/app/assets/export')->assertRedirect();
    }

    public function test_export_can_sort_by_incidents_count(): void
    {
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        Incident::factory()->count(1)->create(['asset_id' => $assetA->id]);
        Incident::factory()->count(3)->create(['asset_id' => $assetB->id]);

        $res = $this->actingAs($this->actor())->get('/app/assets/export?sort=-incidents_count');

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();
        $this->assertStringContainsString('id,name,asset_tag,status,category,manufacturer,model,serial,location,assigned_user,purchase_date,purchase_cost,supplier,order_number,warranty_months,notes,tags', $csv);
    }
}
