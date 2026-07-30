<?php

namespace App\Reports;

use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AbstractReport
{
    /** @var array<string, mixed> */
    protected array $filters = [];

    abstract public static function reportKey(): string;

    abstract public static function reportLabel(): string;

    abstract public static function reportDescription(): string;

    /** A lucide-react icon name. */
    abstract public static function icon(): string;

    /** @return array<int, array<string, mixed>> */
    abstract public function filterConfig(): array;

    abstract public function reportQuery(): Builder;

    /** @return array<int, ReportColumn> */
    abstract public function reportColumns(): array;

    /** @return array<string, mixed> */
    public function defaultFilters(): array
    {
        return [];
    }

    /**
     * Merge incoming filters over defaults, dropping only null/empty-string scalars
     * (keeps '0' and empty arrays, so a numeric 0 or a cleared multiselect survive).
     *
     * @param  array<string, mixed>  $filters
     */
    public function setFilters(array $filters): static
    {
        $clean = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
        $this->filters = array_merge($this->defaultFilters(), $clean);

        return $this;
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function filterSummary(): string
    {
        return '';
    }

    /** @return array<int, string> */
    public function tableSummaries(): array
    {
        return [];
    }

    public function isPaginated(): bool
    {
        return true;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columnMeta(): array
    {
        return array_map(
            static fn (ReportColumn $c): array => ['key' => $c->key, 'label' => $c->label],
            $this->reportColumns(),
        );
    }

    /** @return array<int, string> */
    public function reportHeadings(): array
    {
        return array_map(static fn (ReportColumn $c): string => $c->label, $this->reportColumns());
    }

    /** @return array<int, array<int, mixed>> */
    public function reportRows(): array
    {
        $columns = $this->reportColumns();

        return $this->reportQuery()->get()
            ->map(fn (Model $record): array => array_map(
                static fn (ReportColumn $c) => $c->resolve($record),
                $columns,
            ))
            ->all();
    }

    /** Paginated positional rows for the on-screen list table. */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $columns = $this->reportColumns();
        $paginator = $this->reportQuery()->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(fn (Model $record): array => array_map(
            static fn (ReportColumn $c) => $c->resolve($record),
            $columns,
        ));

        return $paginator;
    }

    /**
     * Column-key => summed value across the FULL result set, for the totals row.
     *
     * @return array<string, float>
     */
    public function totals(): array
    {
        $summaries = $this->tableSummaries();
        if ($summaries === []) {
            return [];
        }

        $records = $this->reportQuery()->get();
        $totals = [];
        foreach ($this->reportColumns() as $column) {
            if (in_array($column->key, $summaries, true)) {
                $totals[$column->key] = (float) $records->sum(fn (Model $r): float => (float) $column->resolve($r));
            }
        }

        return $totals;
    }

    public function pdfView(): string
    {
        return 'pdf.reports.layout';
    }

    /** @return array<string, mixed> */
    public function pdfData(): array
    {
        return [
            'title' => static::reportLabel(),
            'headings' => $this->reportHeadings(),
            'rows' => $this->reportRows(),
            'filterSummary' => $this->filterSummary(),
            'companyName' => config('handover.company.name'),
            'generatedAt' => now()->format('d.m.Y H:i'),
        ];
    }

    public function toPdf(): StreamedResponse
    {
        $pdf = Pdf::loadView($this->pdfView(), $this->pdfData())->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            static::reportKey().'-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function toExport(string $format): StreamedResponse
    {
        return app(ReportExportService::class)->download(
            $format,
            static::reportKey().'-'.now()->format('Y-m-d'),
            $this->reportHeadings(),
            $this->reportRows(),
        );
    }
}
