<?php

namespace Tests\Feature\Support;

use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TableQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_search_across_whitelisted_columns(): void
    {
        Manufacturer::factory()->create(['name' => 'Acme Corp']);
        Manufacturer::factory()->create(['name' => 'Globex']);

        $request = Request::create('/app/manufacturers', 'GET', ['search' => 'acme']);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->searchable(['name'])->sortable(['name'])->paginate();

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Acme Corp', $result->first()->name);
    }

    public function test_sorts_descending_when_the_sort_key_is_prefixed_with_a_dash(): void
    {
        Manufacturer::factory()->create(['name' => 'Alpha']);
        Manufacturer::factory()->create(['name' => 'Zulu']);

        $request = Request::create('/app/manufacturers', 'GET', ['sort' => '-name']);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->sortable(['name'])->paginate();

        $this->assertEquals('Zulu', $result->first()->name);
    }

    public function test_ignores_sort_columns_that_are_not_whitelisted(): void
    {
        Manufacturer::factory()->count(2)->create();

        $request = Request::create('/app/manufacturers', 'GET', ['sort' => 'secret_column']);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->sortable(['name'])->paginate();

        $this->assertEquals(2, $result->total());
    }

    public function test_respects_the_per_page_parameter_and_appends_the_query_string(): void
    {
        Manufacturer::factory()->count(30)->create();

        $request = Request::create('/app/manufacturers', 'GET', ['perPage' => '10', 'search' => 'x']);

        $result = TableQuery::for(Manufacturer::query(), $request)
            ->searchable(['name'])->paginate();

        $this->assertEquals(10, $result->perPage());
        $this->assertStringContainsString('search=x', $result->url(2));
    }

    public function test_clamps_out_of_range_per_page_values_to_the_default(): void
    {
        Manufacturer::factory()->count(2)->create();

        $requestTooLow = Request::create('/app/manufacturers', 'GET', ['perPage' => '0']);
        $resultTooLow = TableQuery::for(Manufacturer::query(), $requestTooLow)->paginate();
        $this->assertEquals(15, $resultTooLow->perPage());

        $requestTooHigh = Request::create('/app/manufacturers', 'GET', ['perPage' => '150']);
        $resultTooHigh = TableQuery::for(Manufacturer::query(), $requestTooHigh)->paginate();
        $this->assertEquals(15, $resultTooHigh->perPage());
    }

    public function test_search_term_with_percent_sign_is_treated_literally(): void
    {
        Manufacturer::factory()->create(['name' => '50% Off']);
        Manufacturer::factory()->create(['name' => 'Acme']);

        $request = Request::create('/app/manufacturers', 'GET', ['search' => '50%']);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->searchable(['name'])->paginate();

        $this->assertEquals(1, $result->total());
        $this->assertEquals('50% Off', $result->first()->name);
    }

    public function test_filterable_applies_exact_match_on_whitelisted_key(): void
    {
        $mA = Manufacturer::factory()->create();
        $mB = Manufacturer::factory()->create();
        AssetModel::factory()->count(2)->create(['manufacturer_id' => $mA->id]);
        AssetModel::factory()->create(['manufacturer_id' => $mB->id]);

        $request = Request::create('/x', 'GET', ['filter' => ['manufacturer_id' => $mA->id]]);
        $result = TableQuery::for(AssetModel::query(), $request)
            ->filterable(['manufacturer_id'])->paginate();

        $this->assertSame(2, $result->total());
    }

    public function test_filterable_maps_request_key_to_qualified_column(): void
    {
        $mA = Manufacturer::factory()->create();
        $mB = Manufacturer::factory()->create();
        AssetModel::factory()->create(['manufacturer_id' => $mA->id]);
        AssetModel::factory()->create(['manufacturer_id' => $mB->id]);

        // A joined query where a bare `manufacturer_id`/`name` would be ambiguous.
        $query = AssetModel::query()
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->select('asset_models.*');
        $request = Request::create('/x', 'GET', ['filter' => ['manufacturer_id' => $mA->id]]);

        $result = TableQuery::for($query, $request)
            ->filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])->paginate();

        $this->assertSame(1, $result->total());
    }

    public function test_filterable_ignores_unwhitelisted_or_empty_filters(): void
    {
        Manufacturer::factory()->count(3)->create();

        $request = Request::create('/x', 'GET', ['filter' => ['bogus' => 'x', 'name' => '']]);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->filterable(['name'])->paginate();

        $this->assertSame(3, $result->total()); // bogus not whitelisted; name empty -> ignored
    }

    public function test_get_returns_filtered_unpaginated_collection(): void
    {
        Manufacturer::factory()->create(['name' => 'Acme']);
        Manufacturer::factory()->create(['name' => 'Globex']);

        $request = Request::create('/x', 'GET', ['search' => 'acme']);
        $result = TableQuery::for(Manufacturer::query(), $request)
            ->searchable(['name'])->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('Acme', $result->first()->name);
    }
}
