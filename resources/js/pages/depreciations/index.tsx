import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import { MONTH_OPTIONS, type Depreciation } from './types';

type Row = Depreciation & { depreciation_type_label: string | null };

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'months', header: 'Months' },
    {
        accessorKey: 'depreciation_min', header: 'Floor value',
        cell: ({ row }) => (row.original.depreciation_type === 'percentage' ? `${row.original.depreciation_min} %` : row.original.depreciation_min),
    },
    { accessorKey: 'depreciation_type', header: 'Floor value type', cell: ({ row }) => <Badge variant="secondary">{row.original.depreciation_type_label}</Badge> },
    { accessorKey: 'rate_multiplier', header: 'Rate multiplier' },
    {
        accessorKey: 'fiscal_year_start_month', header: 'Fiscal year start',
        cell: ({ row }) => MONTH_OPTIONS[row.original.fiscal_year_start_month - 1]?.label ?? '—',
    },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/depreciations/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/depreciations/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function DepreciationsIndex({ depreciations }: { depreciations: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Depreciations" breadcrumbs={[{ label: 'Depreciations' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Depreciations</h1>
                <Button asChild><Link href="/app/depreciations/create">New depreciation</Link></Button>
            </div>
            <DataTable columns={columns} rows={depreciations.data} pagination={depreciations.meta} baseUrl="/app/depreciations"
                sortable={['name', 'depreciation_type', 'months']} rowHref={(r) => `/app/depreciations/${r.id}/edit`} />
        </AppLayout>
    );
}
