// resources/js/pages/reports/show.tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SelectField } from '@/components/form/select-field';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { SwitchField } from '@/components/form/switch-field';
import { MultiSelectField } from '@/components/form/multi-select-field';

interface Opt { value: string; label: string }
interface FilterCfg {
    key: string;
    type: 'multiselect' | 'select' | 'date' | 'number' | 'toggle';
    label: string;
    options?: Opt[];
    nullable?: boolean;
    default?: string | number | boolean;
    min?: number;
}
interface ColumnMeta { key: string; label: string }
interface Pagination { current_page: number; last_page: number; per_page: number; total: number }
type Cell = string | number | null;

interface Props {
    meta: { key: string; label: string; description: string };
    filterConfig: FilterCfg[];
    filters: Record<string, unknown>;
    columns: ColumnMeta[];
    rows: Cell[][];
    filterSummary: string;
    pagination?: Pagination;
    totals?: Record<string, number>;
}

type FilterValue = string | string[] | boolean;

function initialValue(cfg: FilterCfg, current: unknown): FilterValue {
    if (cfg.type === 'multiselect') return Array.isArray(current) ? (current as string[]) : [];
    if (cfg.type === 'toggle') return current === true || current === '1' || current === 1;
    if (current === undefined || current === null) return cfg.default !== undefined ? String(cfg.default) : '';
    return String(current);
}

// Build a query object Inertia serializes as ?filters[key]=… (arrays as filters[key][]=…).
function toParams(values: Record<string, FilterValue>): { filters: Record<string, string | string[]> } {
    const filters: Record<string, string | string[]> = {};
    for (const [k, v] of Object.entries(values)) {
        if (Array.isArray(v)) { if (v.length) filters[k] = v; }
        else if (typeof v === 'boolean') { if (v) filters[k] = '1'; }
        else if (v !== '') filters[k] = v;
    }
    return { filters };
}

// Build a query string for plain-anchor download links.
function toQuery(values: Record<string, FilterValue>, extra: Record<string, string> = {}): string {
    const sp = new URLSearchParams();
    for (const [k, v] of Object.entries(values)) {
        if (Array.isArray(v)) v.forEach((x) => sp.append(`filters[${k}][]`, x));
        else if (typeof v === 'boolean') { if (v) sp.set(`filters[${k}]`, '1'); }
        else if (v !== '') sp.set(`filters[${k}]`, v);
    }
    for (const [k, v] of Object.entries(extra)) sp.set(k, v);
    const s = sp.toString();
    return s ? `?${s}` : '';
}

export default function ReportShow({ meta, filterConfig, filters, columns, rows, filterSummary, pagination, totals }: Props) {
    const [values, setValues] = useState<Record<string, FilterValue>>(() =>
        Object.fromEntries(filterConfig.map((c) => [c.key, initialValue(c, filters[c.key])])),
    );

    const set = (k: string, v: FilterValue) => setValues((prev) => ({ ...prev, [k]: v }));
    const base = `/app/reports/${meta.key}`;

    const apply = () => router.get(base, toParams(values), { preserveScroll: true, preserveState: true });
    const reset = () => router.get(base, {}, { preserveScroll: true });
    const goPage = (page: number) =>
        router.get(base, { ...toParams(values), per_page: pagination?.per_page, page }, { preserveScroll: true, preserveState: true });
    const setPerPage = (per: string) =>
        router.get(base, { ...toParams(values), per_page: per }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout title={meta.label} breadcrumbs={[{ label: 'Reports', href: '/app/reports' }, { label: meta.label }]}>
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{meta.label}</h1>
                    <p className="text-sm text-muted-foreground">{meta.description}</p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline"><a href={`${base}/pdf${toQuery(values)}`}>PDF</a></Button>
                    <Button asChild variant="outline"><a href={`${base}/export${toQuery(values, { format: 'xlsx' })}`}>Excel</a></Button>
                    <Button asChild variant="outline"><a href={`${base}/export${toQuery(values, { format: 'csv' })}`}>CSV</a></Button>
                </div>
            </div>

            {filterConfig.length > 0 && (
                <Card className="mb-4">
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                        {filterConfig.map((c) => {
                            const v = values[c.key];
                            if (c.type === 'multiselect') return <MultiSelectField key={c.key} id={c.key} label={c.label} options={c.options ?? []} value={v as string[]} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'select') return <SelectField key={c.key} id={c.key} label={c.label} options={c.options ?? []} nullable={c.nullable !== false} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'date') return <DateField key={c.key} id={c.key} label={c.label} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'number') return <NumberField key={c.key} id={c.key} label={c.label} step="1" min={c.min !== undefined ? String(c.min) : undefined} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            return <SwitchField key={c.key} id={c.key} label={c.label} checked={v as boolean} onChange={(nv) => set(c.key, nv)} />;
                        })}
                        <div className="flex items-end gap-2">
                            <Button onClick={apply}>Apply</Button>
                            <Button variant="ghost" onClick={reset}>Reset</Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {filterSummary && <p className="mb-2 text-sm text-muted-foreground">{filterSummary}</p>}

            <Card>
                <CardContent className="pt-6">
                    {rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Keine Daten für die gewählten Filter.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>{columns.map((c) => <TableHead key={c.key}>{c.label}</TableHead>)}</TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, i) => (
                                    <TableRow key={i}>
                                        {row.map((cell, j) => <TableCell key={j}>{cell ?? '—'}</TableCell>)}
                                    </TableRow>
                                ))}
                                {totals && Object.keys(totals).length > 0 && (
                                    <TableRow className="font-semibold">
                                        {columns.map((c, j) => (
                                            <TableCell key={c.key}>{j === 0 ? 'Σ' : (totals[c.key] ?? '')}</TableCell>
                                        ))}
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-4 flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {pagination.total} · Seite {pagination.current_page}/{pagination.last_page}
                            </span>
                            <div className="flex items-center gap-2">
                                <select
                                    className="rounded-md border bg-background px-2 py-1"
                                    value={String(pagination.per_page)}
                                    onChange={(e) => setPerPage(e.target.value)}
                                >
                                    {['25', '50', '100'].map((n) => <option key={n} value={n}>{n}</option>)}
                                </select>
                                <Button variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => goPage(pagination.current_page - 1)}>Prev</Button>
                                <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page} onClick={() => goPage(pagination.current_page + 1)}>Next</Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
