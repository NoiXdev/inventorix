<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Reports\AbstractReport;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const PER_PAGE = [25, 50, 100];

    public function index(): Response
    {
        $reports = array_map(
            static fn (string $class): array => [
                'key' => $class::reportKey(),
                'label' => $class::reportLabel(),
                'description' => $class::reportDescription(),
                'icon' => $class::icon(),
            ],
            ReportRegistry::all(),
        );

        return Inertia::render('reports/index', ['reports' => $reports]);
    }

    public function show(string $report, Request $request): Response
    {
        $instance = $this->resolve($report, $request);

        $props = [
            'meta' => [
                'key' => $instance::reportKey(),
                'label' => $instance::reportLabel(),
                'description' => $instance::reportDescription(),
            ],
            'filterConfig' => $instance->filterConfig(),
            'filters' => (object) $instance->filters(),
            'columns' => $instance->columnMeta(),
            'filterSummary' => $instance->filterSummary(),
        ];

        if ($instance->isPaginated()) {
            $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true)
                ? (int) $request->input('per_page')
                : 25;
            $paginator = $instance->paginate($perPage);
            $props['rows'] = $paginator->items();
            $props['pagination'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ];
        } else {
            $props['rows'] = $instance->reportRows();
        }

        if ($instance->tableSummaries() !== []) {
            $props['totals'] = (object) $instance->totals();
        }

        return Inertia::render('reports/show', $props);
    }

    public function pdf(string $report, Request $request): StreamedResponse
    {
        return $this->resolve($report, $request)->toPdf();
    }

    public function export(string $report, Request $request): StreamedResponse
    {
        $format = $request->input('format');
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 422);

        return $this->resolve($report, $request)->toExport($format);
    }

    private function resolve(string $report, Request $request): AbstractReport
    {
        $class = ReportRegistry::find($report);
        abort_unless($class !== null, 404);

        /** @var array<string, mixed> $filters */
        $filters = $request->input('filters', []);

        return app($class)->setFilters(is_array($filters) ? $filters : []);
    }
}
