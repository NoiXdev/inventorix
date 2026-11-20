<?php

namespace Tests\Feature\App;

use App\Enums\CategoryType;
use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use App\Support\Assets\AssetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => '', 'name' => 'Ada laptop', 'asset_tag' => '', 'status' => 'in-use', 'category' => 'Laptop',
            'manufacturer' => 'Acme', 'model' => 'X1', 'serial' => 'SN-1', 'location' => 'Office',
            'assigned_user' => 'Ada Lovelace', 'purchase_date' => '2024-01-05', 'purchase_cost' => '999.50',
            'supplier' => '', 'order_number' => '', 'warranty_months' => '', 'notes' => '', 'tags' => 'portable, audited',
        ], $overrides);
    }

    public function test_imports_a_row_creating_lookups_and_assigned_user(): void
    {
        $result = (new AssetImport)->import([$this->row()]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['failed']);

        $asset = Asset::firstWhere('serial', 'SN-1');
        $this->assertNotNull($asset);
        $this->assertSame(SystemStatus::IN_USE, $asset->status->system_key);
        $this->assertSame('Laptop', $asset->model->category->name);
        $this->assertSame('Acme', $asset->model->manufacturer->name);
        $this->assertSame('X1', $asset->model->name);
        $this->assertSame('Office', $asset->location->name);
        $owner = User::firstWhere('name', 'Ada Lovelace');
        $this->assertNotNull($owner);
        $this->assertSame($owner->id, $asset->assignedUserId());
        $this->assertSame('Ada laptop', $asset->name);
        $this->assertSame('999.50', $asset->purchase_cost);
        $this->assertSame('Ada', $owner->firstname);
        $this->assertSame('Lovelace', $owner->lastname);
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_accepts_enum_label_and_german_date(): void
    {
        $result = (new AssetImport)->import([$this->row(['status' => 'Defekt', 'purchase_date' => '05.01.2024'])]);
        $this->assertSame(1, $result['imported']);
        $asset = Asset::firstWhere('serial', 'SN-1');
        $this->assertSame(SystemStatus::DEFECT, $asset->status->system_key);
        $this->assertSame('2024-01-05', $asset->purchase_date->toDateString());
    }

    public function test_model_without_manufacturer_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['manufacturer' => '', 'model' => 'Orphan'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(2, $result['failed'][0]['row']);
    }

    public function test_category_is_created_as_an_asset_category(): void
    {
        (new AssetImport)->import([$this->row(['category' => 'Docking station'])]);

        $this->assertSame(CategoryType::ASSET, Asset::sole()->model->category->category_type);
    }

    public function test_category_is_not_matched_against_other_category_types(): void
    {
        $accessory = Category::factory()->create(['name' => 'Laptop', 'category_type' => CategoryType::ACCESSORY]);

        (new AssetImport)->import([$this->row(['category' => 'Laptop'])]);

        $this->assertNotSame($accessory->id, Asset::sole()->model->category_id);
        $this->assertSame(CategoryType::ASSET, Asset::sole()->model->category->category_type);
    }

    public function test_category_never_changes_an_existing_model_category(): void
    {
        (new AssetImport)->import([$this->row(['category' => 'Laptop', 'serial' => 'SN-1'])]);
        (new AssetImport)->import([$this->row(['category' => 'Tablet', 'serial' => 'SN-2'])]);

        $this->assertSame(['Laptop', 'Laptop'], Asset::query()->with('model.category')->get()->map(fn (Asset $a) => $a->model->category->name)->all());
        $this->assertSame(0, Category::where('name', 'Tablet')->count());
    }

    public function test_category_without_a_model_is_ignored(): void
    {
        $result = (new AssetImport)->import([$this->row(['category' => 'Laptop', 'manufacturer' => '', 'model' => ''])]);

        $this->assertSame(1, $result['imported']);
        $this->assertNull(Asset::sole()->model_id);
    }

    public function test_failed_row_rolls_back_lookup_records_it_created(): void
    {
        $result = (new AssetImport)->import([$this->row(['category' => 'BrandNewType', 'model' => 'Orphan', 'purchase_cost' => 'bogus'])]);

        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(0, Category::where('name', 'BrandNewType')->count());
        $this->assertSame(0, AssetModel::where('name', 'Orphan')->count());
        $this->assertSame(0, Asset::count());
    }

    public function test_unknown_state_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['status' => 'nonsense'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('nonsense', $result['failed'][0]['message']);
    }

    public function test_invalid_date_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['purchase_date' => 'not-a-date'])]);
        $this->assertSame(0, $result['imported']);
    }

    public function test_existing_id_fails_but_new_id_is_used(): void
    {
        $existing = Asset::factory()->create();
        $dup = (new AssetImport)->import([$this->row(['id' => $existing->id])]);
        $this->assertSame(0, $dup['imported']);

        $uuid = '11111111-1111-4111-8111-111111111111';
        $new = (new AssetImport)->import([$this->row(['id' => $uuid, 'serial' => 'SN-NEW'])]);
        $this->assertSame(1, $new['imported']);
        $this->assertNotNull(Asset::find($uuid));
    }

    public function test_assigned_user_is_matched_case_insensitively_when_existing(): void
    {
        $u = User::factory()->withoutLogin()->create(['name' => 'Ada Lovelace']);
        (new AssetImport)->import([$this->row(['assigned_user' => 'ada lovelace'])]);
        $this->assertSame(1, User::where('name', 'Ada Lovelace')->count()); // not duplicated
        $this->assertSame($u->id, Asset::firstWhere('serial', 'SN-1')->assignee_id);
    }

    public function test_one_bad_row_does_not_block_good_rows(): void
    {
        $result = (new AssetImport)->import([
            $this->row(['serial' => 'GOOD-1']),
            $this->row(['serial' => 'BAD', 'status' => 'nope']),
            $this->row(['serial' => 'GOOD-2']),
        ]);
        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(3, $result['failed'][0]['row']); // 2nd data row -> row 3
        $this->assertNull(Asset::firstWhere('serial', 'BAD'));
        $this->assertNotNull(Asset::firstWhere('serial', 'GOOD-1'));
        $this->assertNotNull(Asset::firstWhere('serial', 'GOOD-2'));
    }

    public function test_new_fields_are_imported_and_validated(): void
    {
        $result = (new AssetImport)->import([$this->row([
            'asset_tag' => 'IT-1', 'supplier' => 'Bechtle', 'order_number' => 'PO-1', 'warranty_months' => '24', 'notes' => 'Spare',
        ])]);
        $this->assertSame(1, $result['imported']);
        $asset = Asset::sole();
        $this->assertSame('IT-1', $asset->asset_tag);
        $this->assertSame('Bechtle', $asset->supplier->name);
        $this->assertSame('PO-1', $asset->order_number);
        $this->assertSame('2026-01-05', $asset->warrantyEnd()->toDateString());
        $this->assertSame('Spare', $asset->notes);

        $again = (new AssetImport)->import([
            $this->row(['asset_tag' => 'IT-1', 'serial' => 'SN-2']),
            $this->row(['warranty_months' => '1.5', 'serial' => 'SN-3']),
        ]);
        $this->assertSame(0, $again['imported']);
        $this->assertStringContainsString('IT-1', $again['failed'][0]['message']);
        $this->assertStringContainsString('warranty', $again['failed'][1]['message']);
    }
}
