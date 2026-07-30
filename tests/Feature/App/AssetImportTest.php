<?php

namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Person;
use App\Support\Assets\AssetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => '', 'state' => 'in-use', 'asset_type' => 'Laptop', 'manufacturer' => 'Acme',
            'model' => 'X1', 'place' => 'Office', 'owner' => 'Ada Lovelace', 'serial_number' => 'SN-1',
            'buy_date' => '2024-01-05', 'guarantee_end' => '', 'buy_price' => '999.50',
            'buy_type' => '', 'tags' => 'portable, audited',
        ], $overrides);
    }

    public function test_imports_a_row_creating_lookups_and_owner(): void
    {
        $result = (new AssetImport)->import([$this->row()]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['failed']);

        $asset = Asset::firstWhere('serial_number', 'SN-1');
        $this->assertNotNull($asset);
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame('Laptop', $asset->assetType->name);
        $this->assertSame('Acme', $asset->model->manufacturer->name);
        $this->assertSame('X1', $asset->model->name);
        $this->assertSame('Office', $asset->place->name);
        $owner = Person::firstWhere('name', 'Ada Lovelace');
        $this->assertNotNull($owner);
        $this->assertSame('Ada', $owner->firstname);
        $this->assertSame('Lovelace', $owner->lastname);
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_accepts_enum_label_and_german_date(): void
    {
        $result = (new AssetImport)->import([$this->row(['state' => 'Defekt', 'buy_date' => '05.01.2024'])]);
        $this->assertSame(1, $result['imported']);
        $asset = Asset::firstWhere('serial_number', 'SN-1');
        $this->assertSame(AssetState::DEFECT, $asset->state);
        $this->assertSame('2024-01-05', $asset->buy_date->toDateString());
    }

    public function test_model_without_manufacturer_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['manufacturer' => '', 'model' => 'Orphan'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(2, $result['failed'][0]['row']);
    }

    public function test_failed_row_rolls_back_lookup_records_it_created(): void
    {
        $result = (new AssetImport)->import([$this->row(['asset_type' => 'BrandNewType', 'manufacturer' => '', 'model' => 'Orphan'])]);

        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(0, AssetType::where('name', 'BrandNewType')->count());
        $this->assertSame(0, Asset::count());
    }

    public function test_unknown_state_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['state' => 'nonsense'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('nonsense', $result['failed'][0]['message']);
    }

    public function test_invalid_date_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['buy_date' => 'not-a-date'])]);
        $this->assertSame(0, $result['imported']);
    }

    public function test_existing_id_fails_but_new_id_is_used(): void
    {
        $existing = Asset::factory()->create();
        $dup = (new AssetImport)->import([$this->row(['id' => $existing->id])]);
        $this->assertSame(0, $dup['imported']);

        $uuid = '11111111-1111-4111-8111-111111111111';
        $new = (new AssetImport)->import([$this->row(['id' => $uuid, 'serial_number' => 'SN-NEW'])]);
        $this->assertSame(1, $new['imported']);
        $this->assertNotNull(Asset::find($uuid));
    }

    public function test_owner_is_matched_case_insensitively_when_existing(): void
    {
        $u = Person::factory()->create(['name' => 'Ada Lovelace']);
        (new AssetImport)->import([$this->row(['owner' => 'ada lovelace'])]);
        $this->assertSame(1, Person::where('name', 'Ada Lovelace')->count()); // not duplicated
        $this->assertSame($u->id, Asset::firstWhere('serial_number', 'SN-1')->owner_id);
    }

    public function test_one_bad_row_does_not_block_good_rows(): void
    {
        $result = (new AssetImport)->import([
            $this->row(['serial_number' => 'GOOD-1']),
            $this->row(['serial_number' => 'BAD', 'state' => 'nope']),
            $this->row(['serial_number' => 'GOOD-2']),
        ]);
        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(3, $result['failed'][0]['row']); // 2nd data row -> row 3
        $this->assertNull(Asset::firstWhere('serial_number', 'BAD'));
        $this->assertNotNull(Asset::firstWhere('serial_number', 'GOOD-1'));
        $this->assertNotNull(Asset::firstWhere('serial_number', 'GOOD-2'));
    }
}
