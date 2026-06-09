<?php

namespace App\Filament\App\Pages\Evaluation;

use App\Reports\ReportColumn;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.evaluation.report';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    // ---- Report contract -------------------------------------------------

    abstract public static function reportKey(): string;

    abstract public static function reportLabel(): string;

    abstract public static function reportDescription(): string;

    abstract public static function reportIcon(): string;

    /** @return array<int, \Filament\Forms\Components\Component> */
    abstract protected function filterSchema(): array;

    abstract protected function reportQuery(): Builder;

    /** @return array<int, ReportColumn> */
    abstract protected function reportColumns(): array;

    /** A short human-readable summary of the active filters, shown on the PDF. */
    protected function filterSummary(): string
    {
        return '';
    }

    // ---- Filament wiring -------------------------------------------------

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return static::reportLabel();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->filterSchema())
            ->statePath('filters');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->reportQuery())
            ->columns(array_map(
                fn (ReportColumn $column): TextColumn => TextColumn::make($column->key)
                    ->label($column->label)
                    ->state(fn (Model $record) => $column->resolve($record)),
                $this->reportColumns(),
            ))
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('evaluation.actions.pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (): StreamedResponse => $this->downloadPdf()),
            ActionGroup::make([
                Action::make('xlsx')
                    ->label('Excel (XLSX)')
                    ->action(fn (): StreamedResponse => $this->export('xlsx')),
                Action::make('csv')
                    ->label('CSV')
                    ->action(fn (): StreamedResponse => $this->export('csv')),
            ])
                ->label(__('evaluation.actions.export'))
                ->icon('heroicon-o-table-cells')
                ->button(),
        ];
    }

    // ---- Output renderers ------------------------------------------------

    /** @return array<int, string> */
    protected function reportHeadings(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->label, $this->reportColumns());
    }

    /** @return array<int, array<int, mixed>> */
    protected function reportRows(): array
    {
        $columns = $this->reportColumns();

        return $this->reportQuery()->get()
            ->map(fn (Model $record): array => array_map(
                fn (ReportColumn $column) => $column->resolve($record),
                $columns,
            ))
            ->all();
    }

    public function downloadPdf(): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.reports.layout', [
            'title' => static::reportLabel(),
            'headings' => $this->reportHeadings(),
            'rows' => $this->reportRows(),
            'filterSummary' => $this->filterSummary(),
            'companyName' => config('handover.company.name'),
            'generatedAt' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            static::reportKey().'-'.now()->format('Y-m-d').'.pdf',
        );
    }

    public function export(string $format): StreamedResponse
    {
        return app(ReportExportService::class)->download(
            $format,
            static::reportKey().'-'.now()->format('Y-m-d'),
            $this->reportHeadings(),
            $this->reportRows(),
        );
    }
}
