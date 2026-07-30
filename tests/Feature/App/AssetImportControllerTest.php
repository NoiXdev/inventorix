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

    private const HEADER = "id,state,asset_type,manufacturer,model,place,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags\n";

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
