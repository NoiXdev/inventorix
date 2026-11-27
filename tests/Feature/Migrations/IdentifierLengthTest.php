<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests run on SQLite, which accepts any identifier length; production runs
 * on MariaDB, which refuses names over 64 characters (a migration then fails
 * half-way). Every index and foreign key name has to fit.
 */
class IdentifierLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_and_foreign_key_names_fit_mariadb(): void
    {
        $tooLong = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $tooLong[] = strlen($table) > 64 ? "table {$table}" : null;
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen($index['name']) > 64) {
                    $tooLong[] = "{$table}: index {$index['name']}";
                }
            }
            foreach (Schema::getForeignKeys($table) as $key) {
                if ($key['name'] !== null && strlen($key['name']) > 64) {
                    $tooLong[] = "{$table}: foreign key {$key['name']}";
                }
            }
        }

        $this->assertSame([], array_values(array_filter($tooLong)), 'Identifiers longer than 64 characters');
    }
}
