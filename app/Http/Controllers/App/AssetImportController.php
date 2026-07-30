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
        if ($header === false || ! $this->headerMatches($header)) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'The CSV header must be exactly: '.implode(', ', AssetCsv::HEADERS).'.',
            ]);
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
                continue; // skip blank lines
            }
            $rows[] = array_combine(AssetCsv::HEADERS, array_pad(array_slice($line, 0, count(AssetCsv::HEADERS)), count(AssetCsv::HEADERS), ''));
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

    /** @param array<int, string|null> $header */
    private function headerMatches(array $header): bool
    {
        // Trim BOM/whitespace on each cell, then require an exact, order-sensitive match.
        $normalized = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\u{FEFF}"), $header);

        return $normalized === AssetCsv::HEADERS;
    }
}
