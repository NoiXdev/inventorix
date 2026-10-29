<?php

namespace Tests\Feature\Seeders;

use App\Models\Manufacturer;
use Database\Seeders\ManufacturerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManufacturerSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function runSeeder(): void
    {
        $this->artisan('db:seed', ['--class' => ManufacturerSeeder::class]);
    }

    public function test_it_creates_a_manufacturer_per_curated_entry(): void
    {
        $this->runSeeder();

        $this->assertSame(
            count(ManufacturerSeeder::entries()),
            Manufacturer::count(),
        );
        $this->assertDatabaseHas('manufacturers', ['name' => 'Dell']);
        $this->assertDatabaseHas('manufacturers', ['name' => 'Apple']);
    }

    public function test_it_fills_the_detail_fields(): void
    {
        $this->runSeeder();

        $dell = Manufacturer::where('name', 'Dell')->firstOrFail();

        $this->assertNotNull($dell->url);
        $this->assertNotNull($dell->support_url);
        $this->assertNotNull($dell->support_email);
    }

    public function test_it_copies_each_logo_onto_the_upload_disk(): void
    {
        $this->runSeeder();

        foreach (Manufacturer::all() as $manufacturer) {
            $this->assertNotNull($manufacturer->image_path, "{$manufacturer->name} has no logo");
            $this->assertStringStartsWith('manufacturers/', $manufacturer->image_path);
            Storage::assertExists($manufacturer->image_path);
        }
    }

    public function test_the_icon_variants_do_not_become_manufacturers(): void
    {
        $this->runSeeder();

        $this->assertDatabaseMissing('manufacturers', ['name' => 'Dellicon']);
        $this->assertDatabaseMissing('manufacturers', ['name' => 'Lenovoicon']);
    }

    public function test_running_twice_duplicates_neither_rows_nor_files(): void
    {
        $this->runSeeder();

        $rows = Manufacturer::count();
        $files = count(Storage::disk()->allFiles('manufacturers'));
        $paths = Manufacturer::pluck('image_path', 'name')->all();

        $this->runSeeder();

        $this->assertSame($rows, Manufacturer::count());
        $this->assertSame($files, count(Storage::disk()->allFiles('manufacturers')), 'a second run re-copied the logos');
        $this->assertSame($paths, Manufacturer::pluck('image_path', 'name')->all());
    }

    public function test_a_second_run_restores_a_logo_that_went_missing(): void
    {
        $this->runSeeder();

        $dell = Manufacturer::where('name', 'Dell')->firstOrFail();
        Storage::disk()->delete($dell->image_path);

        $this->runSeeder();

        Storage::assertExists(Manufacturer::where('name', 'Dell')->firstOrFail()->image_path);
    }

    public function test_a_missing_source_file_does_not_abort_the_seed(): void
    {
        (new SeederWithMissingAsset)->setContainer($this->app)->run();

        $this->assertDatabaseHas('manufacturers', ['name' => 'Ghost', 'image_path' => null]);
    }

    public function test_every_curated_entry_points_at_a_file_that_exists(): void
    {
        // Guards against a typo in the list silently producing a logo-less row.
        foreach (ManufacturerSeeder::entries() as $entry) {
            $this->assertFileExists(
                database_path('assets/manufacturers/'.$entry['image']),
                "the list references a missing asset: {$entry['image']}",
            );
        }
    }
}

class SeederWithMissingAsset extends ManufacturerSeeder
{
    /** @return array<int, array<string, mixed>> */
    public static function entries(): array
    {
        return [[
            'name' => 'Ghost',
            'url' => 'https://ghost.test',
            'support_url' => null,
            'warranty_lookup_url' => null,
            'support_phone' => null,
            'support_email' => null,
            'notes' => null,
            'image' => 'does-not-exist.png',
        ]];
    }
}
