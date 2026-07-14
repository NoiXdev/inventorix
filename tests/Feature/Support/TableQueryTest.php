<?php

namespace Tests\Feature\Support;

use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_respects_the_perPage_parameter_and_appends_the_query_string(): void
    {
        Manufacturer::factory()->count(30)->create();

        $request = Request::create('/app/manufacturers', 'GET', ['perPage' => '10', 'search' => 'x']);
        $this->app->instance('request', $request);

        $result = TableQuery::for(Manufacturer::query(), $request)
            ->searchable(['name'])->paginate();

        $this->assertEquals(10, $result->perPage());
        $this->assertStringContainsString('search=x', $result->url(2));
    }
}
