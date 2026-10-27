<?php

namespace App\Support\Migrations;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refuses to re-point a foreign key when some referenced id has no row in the
 * target table. Only reachable when a row was created outside the 10a backfill
 * (a user linked to a person with a different UUID, or a person inserted after
 * the merge ran). Failing before any schema change leaves the database usable.
 */
final class IdentityReferenceGuard
{
    /**
     * @param  array<int, array{0: string, 1: string}>  $refs  [table, column] pairs
     */
    public static function assertAllResolve(array $refs, string $target = 'users'): void
    {
        foreach ($refs as [$table, $column]) {
            $missing = DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, fn ($q) => $q->select('id')->from($target))
                ->pluck($column)
                ->unique()
                ->take(10)
                ->all();

            if ($missing !== []) {
                throw new RuntimeException(
                    "Cannot re-point {$table}.{$column} to {$target}: ".count($missing).
                    ' referenced id(s) have no matching row, e.g. '.implode(', ', $missing).
                    '. Merge or reassign these identities, then re-run the migration.'
                );
            }
        }
    }
}
