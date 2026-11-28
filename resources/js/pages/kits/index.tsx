import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; items_count: number }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'items_count', header: 'Lines' },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Can permission="kits.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/kits/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="kits.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/kits/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

export default function KitsIndex({ kits }: { kits: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Kits" breadcrumbs={[{ label: 'Kits' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Kits</h1>
                <Can permission="kits.create"><Button asChild><Link href="/app/kits/create">New kit</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={kits.data} pagination={kits.meta} baseUrl="/app/kits"
                sortable={['name']} rowHref={(r) => `/app/kits/${r.id}`} />
        </AppLayout>
    );
}
