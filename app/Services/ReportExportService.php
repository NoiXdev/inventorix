<?php

namespace App\Services;

use InvalidArgumentException;
use League\Csv\Writer as CsvWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function download(string $format, string $filename, array $headings, array $rows): StreamedResponse
    {
        return match ($format) {
            'csv' => $this->streamCsv($filename, $headings, $rows),
            'xlsx' => $this->streamXlsx($filename, $headings, $rows),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function csvContent(array $headings, array $rows): string
    {
        $csv = CsvWriter::createFromString();
        $csv->insertOne($headings);
        $csv->insertAll(array_map(
            fn (array $row): array => array_map(static fn ($cell): string => (string) ($cell ?? ''), $row),
            $rows,
        ));

        return $csv->toString();
    }

    private function streamCsv(string $filename, array $headings, array $rows): StreamedResponse
    {
        $content = $this->csvContent($headings, $rows);

        return response()->streamDownload(
            fn () => print ($content),
            "{$filename}.csv",
            ['Content-Type' => 'text/csv'],
        );
    }

    private function streamXlsx(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($headings, $rows): void {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                $writer->addRow(Row::fromValues($headings));
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues(array_map(
                        static fn ($cell) => $cell ?? '',
                        $row,
                    )));
                }
                $writer->close();
            },
            "{$filename}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
