<?php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssetImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('assets.csv', $body);
    }

    /** The columns of files exported before the asset fields were reworked; still accepted. */
    private const HEADER = "id,status,category,manufacturer,model,location,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags\n";

    private const CURRENT_HEADER = "id,name,asset_tag,status,category,manufacturer,model,serial,location,assigned_user,purchase_date,purchase_cost,supplier,order_number,warranty_months,notes,tags\n";

    public function test_import_creates_assets_and_flashes_summary(): void
    {
        $body = self::HEADER
            .",in-use,Laptop,Acme,X1,Office,,SN-A,2024-01-05,,100,,\n"
            .",storage,Monitor,Dell,U27,,,SN-B,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertRedirect('/app/assets')
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 2 && $r['failed'] === []);

        $this->assertSame(2, Asset::count());
    }

    public function test_import_reports_failed_rows(): void
    {
        $body = self::HEADER
            .",in-use,Laptop,Acme,X1,,,SN-A,,,,,\n"
            .",nonsense,Laptop,Acme,X1,,,SN-B,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 1 && count($r['failed']) === 1);
    }

    public function test_import_accepts_the_old_place_and_state_headers(): void
    {
        $body = str_replace([',location,', 'id,status,'], [',place,', 'id,state,'], self::HEADER)
            .",in-use,Laptop,Acme,X1,Office,,SN-A,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHasNoErrors();

        $this->assertSame('Office', Asset::sole()->location->name);
    }

    public function test_import_accepts_the_old_asset_type_header(): void
    {
        $body = str_replace(',category,', ',asset_type,', self::HEADER)
            .",in-use,Laptop,Acme,X1,Office,,SN-A,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHasNoErrors();

        $this->assertSame('Laptop', Asset::sole()->model->category->name);
    }

    public function test_import_reads_the_current_columns(): void
    {
        $body = self::CURRENT_HEADER
            .",Ada laptop,IT-1,in-use,Laptop,Acme,X1,SN-A,Office,Ada Lovelace,2024-01-05,999.50,Bechtle,PO-1,24,Spare,\"a, b\"\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 1 && $r['failed'] === []);

        $asset = Asset::sole();
        $this->assertSame('Ada laptop', $asset->name);
        $this->assertSame('IT-1', $asset->asset_tag);
        $this->assertSame('Ada Lovelace', $asset->assignee->name);
        $this->assertSame('Bechtle', $asset->supplier->name);
        $this->assertSame(24, $asset->warranty_months);
    }

    public function test_legacy_owner_and_guarantee_end_become_assignee_and_warranty_months(): void
    {
        $body = self::HEADER.",in-use,Laptop,Acme,X1,Office,Ada Lovelace,SN-A,2024-01-05,2027-01-05,100,once,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 1);

        $asset = Asset::sole();
        $this->assertSame('Ada Lovelace', $asset->assignee->name);
        $this->assertSame('SN-A', $asset->serial);
        $this->assertSame(36, $asset->warranty_months);
        $this->assertSame('100.00', $asset->purchase_cost);
    }

    public function test_import_rejects_wrong_header(): void
    {
        $body = "foo,bar\n1,2\n";
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Asset::count());
    }

    public function test_import_rejects_over_row_cap(): void
    {
        $rows = str_repeat(",in-use,Laptop,Acme,X1,,,S,,,,,\n", 2001);
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv(self::HEADER.$rows)])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Asset::count());
    }

    public function test_import_rejects_bad_mime(): void
    {
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_import_requires_auth(): void
    {
        $this->post('/app/assets/import', [])->assertRedirect();
    }
}
