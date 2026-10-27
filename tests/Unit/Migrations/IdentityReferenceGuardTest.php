<?php

namespace Tests\Unit\Migrations;

use App\Support\Migrations\IdentityReferenceGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IdentityReferenceGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No FK on `ref` on purpose: the guard exists to catch exactly the rows
        // a foreign key would have rejected.
        Schema::create('guard_probe', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ref')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('guard_probe');
        parent::tearDown();
    }

    private function probe(?string $ref): void
    {
        DB::table('guard_probe')->insert(['id' => (string) Str::uuid(), 'ref' => $ref]);
    }

    public function test_it_passes_when_every_reference_resolves(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->probe($user->id);
        $this->probe(null);

        IdentityReferenceGuard::assertAllResolve([['guard_probe', 'ref']]);

        $this->addToAssertionCount(1);
    }

    public function test_it_aborts_naming_the_table_column_and_offending_id(): void
    {
        $orphan = (string) Str::uuid();
        $this->probe($orphan);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/guard_probe\.ref/');
        $this->expectExceptionMessageMatches('/'.preg_quote($orphan, '/').'/');

        IdentityReferenceGuard::assertAllResolve([['guard_probe', 'ref']]);
    }

    public function test_a_null_reference_is_never_an_error(): void
    {
        $this->probe(null);

        IdentityReferenceGuard::assertAllResolve([['guard_probe', 'ref']]);

        $this->addToAssertionCount(1);
    }
}
