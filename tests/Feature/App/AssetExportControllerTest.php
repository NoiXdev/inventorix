<?php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Incident;
use App\Models\Manufacturer;
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
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $type = AssetType::factory()->create(['name' => 'Laptop']);
        $asset = Asset::factory()->create([
            'asset_type_id' => $type->id, 'model_id' => $model->id, 'owner_id' => null,
            'state' => AssetState::IN_USE->value, 'serial_number' => 'SN-9', 'buy_date' => '2024-01-05',
        ]);
        $asset->syncTags(['a', 'b']);

        $res = $this->actingAs($this->actor())->get('/app/assets/export');
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();

        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertStringContainsString('id,state,asset_type,manufacturer,model,place,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags', $lines[0]);
        $this->assertStringContainsString('Laptop', $csv);
        $this->assertStringContainsString('Acme', $csv);
        $this->assertStringContainsString('X1', $csv);
        $this->assertStringContainsString(AssetState::IN_USE->getLabel(), $csv);
        $this->assertStringContainsString('2024-01-05', $csv);
        $this->assertStringContainsString('"a, b"', $csv); // tags joined, quoted by fputcsv
    }

    public function test_export_respects_state_filter(): void
    {
        Asset::factory()->create(['state' => AssetState::IN_USE->value, 'serial_number' => 'KEEP']);
        Asset::factory()->create(['state' => AssetState::DEFECT->value, 'serial_number' => 'DROP']);

        $csv = $this->actingAs($this->actor())->get('/app/assets/export?filter[state]=in-use')->streamedContent();

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
        $this->assertStringContainsString('id,state,asset_type,manufacturer,model,place,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags', $csv);
    }
}
