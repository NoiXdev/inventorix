<?php

namespace Tests\Unit\Reports;

use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    private function service(): ReportExportService
    {
        return new ReportExportService();
    }

    public function test_csv_content_has_heading_row_and_data_rows(): void
    {
        $csv = $this->service()->csvContent(
            ['Name', 'Price'],
            [['Laptop', '999'], ['Mouse', '25']],
        );

        $this->assertStringContainsString('Name,Price', $csv);
        $this->assertStringContainsString('Laptop,999', $csv);
        $this->assertStringContainsString('Mouse,25', $csv);
    }

    public function test_download_csv_returns_streamed_response_with_filename(): void
    {
        $response = $this->service()->download('csv', 'report-2026-06-09', ['Name'], [['Laptop']]);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString(
            'report-2026-06-09.csv',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_download_xlsx_streams_a_zip_based_xlsx(): void
    {
        $response = $this->service()->download('xlsx', 'report', ['Name'], [['Laptop']]);

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        // XLSX is a ZIP archive; it starts with the "PK" signature.
        $this->assertStringStartsWith('PK', $body);
        $this->assertStringContainsString(
            'report.xlsx',
            $response->headers->get('content-disposition'),
        );
    }
}
