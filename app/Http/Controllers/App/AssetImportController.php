<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetImportRequest;
use App\Support\Assets\AssetCsv;
use App\Support\Assets\AssetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AssetImportController extends Controller
{
    private const ROW_CAP = 2000;

    public function store(AssetImportRequest $request, AssetImport $import): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        $header = fgetcsv($handle);
        $columns = $header === false ? null : $this->columnsFor($header);
        if ($columns === null) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'The CSV header must be exactly: '.implode(', ', AssetCsv::HEADERS).'.',
            ]);
        }
        $legacy = $columns === AssetCsv::LEGACY_HEADERS;

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
                continue; // skip blank lines
            }
            $row = array_combine($columns, array_pad(array_slice($line, 0, count($columns)), count($columns), ''));
            $rows[] = $legacy ? AssetCsv::fromLegacy($row) : $row;
            if (count($rows) > self::ROW_CAP) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => 'Too many rows (max '.self::ROW_CAP.'). Split the file; a queued import is a later feature.',
                ]);
            }
        }
        fclose($handle);

        $result = $import->import($rows);

        return to_route('app.assets.index')->with('importResult', $result);
    }

    /**
     * The columns the header stands for: the current ones, or the legacy ones
     * of older exports. Null when it matches neither.
     *
     * @param  array<int, string|null>  $header
     * @return list<string>|null
     */
    private function columnsFor(array $header): ?array
    {
        // Trim BOM/whitespace on each cell, then require an exact, order-sensitive match.
        $normalized = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\u{FEFF}"), $header);
        if ($normalized === AssetCsv::HEADERS) {
            return AssetCsv::HEADERS;
        }

        // Older files say "place", "state" and "asset_type" where later ones
        // say "location", "status" and "category".
        $normalized = array_map(fn (string $h) => match ($h) {
            'place' => 'location', 'state' => 'status', 'asset_type' => 'category', default => $h
        }, $normalized);

        return $normalized === AssetCsv::LEGACY_HEADERS ? AssetCsv::LEGACY_HEADERS : null;
    }
}
