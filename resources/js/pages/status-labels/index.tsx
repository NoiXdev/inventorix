import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Check, Lock, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import { StatusBadge } from '@/components/assets/status-badge';

interface Row {
    id: string; name: string | null; type: string; type_label: string;
    show_in_nav: boolean; default_label: boolean; system: boolean; assets_count: number;
}

const flag = (on: boolean) => (on ? <Check className="h-4 w-4" aria-label="Yes" /> : <span className="text-muted-foreground">—</span>);

const columns: ColumnDef<Row>[] = [
    {
        accessorKey: 'name', header: 'Name',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <StatusBadge status={{ id: row.original.id, name: row.original.name, type: row.original.type }} />
                {row.original.system && <Lock className="h-3.5 w-3.5 text-muted-foreground" aria-label="Used by handovers" />}
            </div>
        ),
    },
    { id: 'type', header: 'Type', cell: ({ row }) => row.original.type_label },
    { accessorKey: 'assets_count', header: 'Assets', cell: ({ row }) => row.original.assets_count },
    { id: 'default_label', header: 'Default', cell: ({ row }) => flag(row.original.default_label) },
    { id: 'show_in_nav', header: 'In navigation', cell: ({ row }) => flag(row.original.show_in_nav) },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/status-labels/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" disabled={row.original.system}
                    title={row.original.system ? 'Used by handovers; can be renamed but not deleted' : undefined}
                    onClick={() => {
                        const n = row.original.assets_count;
                        const warning = n > 0 ? `\n\n${n} asset${n === 1 ? '' : 's'} will keep this status.` : '';
                        if (confirm(`Delete ${row.original.name ?? 'this status'}?${warning}`)) router.delete(`/app/status-labels/${row.original.id}`);
                    }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function StatusLabelsIndex({ labels }: { labels: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Status labels" breadcrumbs={[{ label: 'Status labels' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Status labels</h1>
                <Button asChild><Link href="/app/status-labels/create">New status label</Link></Button>
            </div>
            <DataTable columns={columns} rows={labels.data} pagination={labels.meta} baseUrl="/app/status-labels"
                sortable={['name', 'assets_count']} rowHref={(r) => `/app/status-labels/${r.id}/edit`} />
        </AppLayout>
    );
}
