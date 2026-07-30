# Inertia Migration — Spec 6b: Reports (Evaluation)

**Date:** 2026-07-16
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 6 (Dashboard & Reports), sub-spec **6b of 6a–6b**
**Depends on:** Specs 1–6a (kit, auth, Assets/Handovers/Incidents, form kit, dashboard). Filament remains at `/app-old`.

## Background

Filament ships 8 "Evaluation" reports under `app/Filament/App/Pages/Evaluation/`, each a
`BaseReportPage` (a Filament `Page` implementing `HasForms`+`HasTable`). Each report defines:
`reportKey/Label/Description/Icon`, `filterSchema()` (Filament form components),
`reportQuery(): Builder`, `reportColumns(): array<ReportColumn>`, and optionally
`filterSummary()`, `tableSummaries()`, `isTablePaginated()`, `pdfView()`, `pdfData()`.

The domain-level pieces are already framework-agnostic:
- `App\Reports\ReportColumn` — `{ key, label, Closure value }` + `resolve($record)`.
- `App\Services\ReportExportService` — CSV (League\Csv) + XLSX (OpenSpout) streamed downloads.
- `resources/views/pdf/reports/layout.blade.php` (flat table) and
  `assets-per-employee.blade.php` (grouped, page-break-per-employee) — plain dompdf views.
- `lang/de/evaluation.php` — all labels/columns/filters/PDF text (German only).

Only the Filament `Page`/`Table`/form wiring and `filterSchema()` are UI-coupled.

The 8 reports:
| key | model | filters | shape |
|---|---|---|---|
| `assets_per_employee` | Asset | employees (multiselect) | list; **custom grouped PDF** (page/employee) |
| `guarantee_status` | Asset | status (multiselect), employees (multiselect) | list; computed status + days_left |
| `inventory_by_location` | Asset | places (multiselect) | list |
| `asset_value` | Asset | group_by (select: employee/asset_type/state, default employee), detailed (toggle) | **aggregation** (grouped counts+sum) OR detailed list; PDF appends grand-total row |
| `state_overview` | Asset | — | **aggregation** (per state), non-paginated |
| `incident_history` | Incident | from/to (date), status (select: open/closed) | list |
| `asset_aging` | Asset | min_age_years (number, default 3) | list |
| `handover_history` | Handover | type (multiselect enum), from/to (date) | list |

## Locked decisions

- **Parallel framework-agnostic port.** New backend under `app/Reports/`; Filament report
  pages untouched at `/app-old` until cutover. No changes to `ReportColumn`,
  `ReportExportService`, the PDF blades, or the lang file.
- **German content, resolved server-side.** Reuse `lang/de/evaluation.php`; the controller
  resolves `__('evaluation…')` and passes strings to React. (English shell + German report
  bodies, matching existing PDFs.)
- **Filters: Apply button (+ Reset), URL-driven.** Filters live in the query string
  (`?filters[…]=…`); Apply issues an Inertia GET; Reset clears to defaults. Shareable URLs.
- **Table: paginate lists, full aggregations.** List reports paginate on screen (default 25;
  25/50/100); aggregation reports (`state_overview`, `asset_value` non-detailed) render all
  rows. A totals row shows only where `tableSummaries()` is non-empty. **PDF/CSV/XLSX always
  export the full, unpaginated result** (unchanged from Filament).
- **Single "Reports" nav group** with one **Reports** entry → an index listing the 8 reports.
- Report icons mapped to **lucide** equivalents of the existing heroicons.
- No policy (gate on `auth`); PHPUnit; Vitest; ddev; pnpm.

## Goals

- All 8 evaluation reports available at `/app/reports` with filters, a paginated/aggregated
  results table, and PDF/CSV/XLSX downloads — byte-for-byte the same query results and PDFs
  as Filament. `/app-old` unchanged.

## Non-goals

- New reports beyond the 8; scheduling/emailing reports; per-column sorting (reports keep the
  order defined by `reportQuery()`, as in Filament); charts; DB/schema changes; touching the
  Filament report pages, `ReportColumn`, `ReportExportService`, the blades, or the lang file.

## Design

### Backend — `app/Reports/`

**`AbstractReport`** (framework-agnostic base; ports the non-Filament half of `BaseReportPage`):
- `protected array $filters = [];` + `setFilters(array $filters): static` (merges over `defaultFilters()`).
- Static: `reportKey()/reportLabel()/reportDescription(): string`; `icon(): string` (lucide name).
- Abstract instance: `filterConfig(): array`, `reportQuery(): Builder`, `reportColumns(): array<ReportColumn>`.
- Defaults (overridable): `defaultFilters(): array` (`[]`), `filterSummary(): string` (`''`),
  `tableSummaries(): array` (`[]`), `isPaginated(): bool` (`true`).
- Ported verbatim from `BaseReportPage`: `reportHeadings()`, `reportRows()` (maps full `->get()`),
  `pdfView()` (`'pdf.reports.layout'`), `pdfData()`, `downloadPdf()→toPdf()`, `export()→toExport($format)`.
- New helpers for the controller: `columnMeta(): array` (`[{key,label}]`);
  `paginate(int $perPage): LengthAwarePaginator` (paginates `reportQuery()`, transforms items via
  `reportColumns()` into positional arrays aligned to `columnMeta()`);
  `totals(): array<string,float>` (for each `tableSummaries()` key, sums the resolved column across
  all rows; empty when no summaries).

**`ReportRegistry`** (`app/Reports/`): `all(): array<class-string<AbstractReport>>` — the 8 new
classes in display order; `find(string $key): ?class-string` — key → class.

**`ReportController`** (authenticated `/app` group, names `app.reports.*`):
- `GET reports` → `reports/index` with `reports: [{ key, label, description, icon }]`.
- `GET reports/{report}` → resolve `{report}` (key) via registry (404 if unknown); build
  `$report = app($class)->setFilters($request->input('filters', []))`; render `reports/show` with:
  `meta {key,label,description}`, `filterConfig`, `filters` (the merged active values),
  `columns` (`columnMeta`), `filterSummary`, and either
  `rows` + `pagination {current_page,last_page,per_page,total}` (paginated list, `per_page` from
  request clamped to 25/50/100) **or** `rows` + `totals` (non-paginated aggregation).
- `GET reports/{report}/pdf` → `$report->setFilters(...)->toPdf()` (StreamedResponse).
- `GET reports/{report}/export` → validate `format ∈ {csv,xlsx}`; `$report->toExport($format)`.
- Filters merge over `defaultFilters()` so first load applies defaults (e.g. `min_age_years=3`,
  `group_by=employee`).

**Routes:** register `reports`, `reports/{report}`, `reports/{report}/pdf`,
`reports/{report}/export` (the `{report}` is a plain key string, not a model binding).

**8 report classes** (`app/Reports/*Report.php`) — each **copies its Filament twin's
`reportQuery()`, `reportColumns()`, `filterSummary()`, `pdfData()`, and private helpers verbatim**
(only namespace/parent change), and:
- replaces `filterSchema()` (Filament components) with `filterConfig()` (a serializable array;
  the exact per-report shape is specified in the plan),
- declares `icon()` (lucide), `defaultFilters()`, and `isPaginated()` where the Filament twin
  overrode `isTablePaginated()`.

**`filterConfig()` entry shapes** (React renders each with the form kit):
- multiselect: `{ key, type:'multiselect', label, options:[{value,label}] }`
- select: `{ key, type:'select', label, options:[{value,label}], nullable:bool, default? }`
- date: `{ key, type:'date', label }`
- number: `{ key, type:'number', label, default?, min? }`
- toggle: `{ key, type:'toggle', label, default? }`

Options for `employees`/`places` come from `User`/`Place` ordered by name (`{value:id,label:name}`);
`handover_history.type` from `HandoverType::cases()` (`{value, label:getLabel()}`); enum/status
selects from the lang file.

### Frontend — `resources/js/pages/reports/`

- **`index.tsx`** — a responsive grid of report `Card`s (lucide icon + label + description),
  each linking to `app.reports.show`. New **Reports** sidebar group + **Reports** nav entry.
- **`show.tsx`** — a **filter panel** built from `filterConfig` using `SelectField`, `DateField`,
  `NumberField`, `SwitchField`, and a new **`MultiSelectField`** (searchable checkbox-list
  popover) for multiselects; **Apply** (Inertia GET with `filters` in the URL) + **Reset**
  (to defaults). Below: a results `Table` (shadcn) whose headers come from `columns`, rows from
  `rows` (positional arrays); a **totals row** when `totals` is present; a **pagination footer**
  (prev/next + page-size) when `pagination` is present. **PDF / Excel / CSV** download buttons are
  anchors to `app.reports.pdf` / `app.reports.export?format=…` carrying the current filters. Empty
  state when no rows.

### Reuse

`ReportColumn`, `ReportExportService`, the two PDF blades, `lang/de/evaluation.php` (untouched);
`Card`, `Table`, `Button`, `SelectField`, `DateField`, `NumberField`, `SwitchField`. New:
`AbstractReport`, `ReportRegistry`, `ReportController`, 8 report classes, `MultiSelectField`, the
two React pages.

## Testing

- **PHPUnit `ReportControllerTest`**: index lists 8; unknown key → 404; guest → redirect.
- **PHPUnit per report** (or grouped): each `show` returns the expected `columns`/`rows` shape;
  filters actually filter — guarantee `status`/`employees`; inventory `places`; asset_value
  `group_by`+`detailed` (aggregation vs detailed rows, totals); state_overview non-paginated per
  state; incident `from`/`to`/`status`; asset_aging `min_age_years` cutoff; handover `type`+date
  range; assets_per_employee grouping in the PDF data. List pagination (`per_page`, page meta);
  aggregation `totals`. `pdf` streams a PDF (`application/pdf`); `export?format=csv|xlsx` streams
  with the right content-type; bad format rejected.
- **Vitest**: `reports/show` renders fields from a sample `filterConfig`, Apply pushes `filters`
  to the URL, table + totals + pagination render, download links carry the filters; `reports/index`
  lists report cards; `MultiSelectField` selects/clears options.

## Open questions / follow-ups (later)

- English translations for reports (currently German); per-column sorting; scheduled/emailed
  reports; charts. Cutover (Spec 9) deletes the Filament Evaluation pages; the `app/Reports/`
  classes, `ReportColumn`, `ReportExportService`, and blades remain.
