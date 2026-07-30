import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/asset-types/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/asset-types/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function AssetTypesIndex({ assetTypes }: { assetTypes: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Asset types" breadcrumbs={[{ label: 'Asset types' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Asset types</h1>
                <Button asChild><Link href="/app/asset-types/create">New asset type</Link></Button>
            </div>
            <DataTable columns={columns} rows={assetTypes.data} pagination={assetTypes.meta} baseUrl="/app/asset-types" sortable={['name']} rowHref={(r) => `/app/asset-types/${r.id}/edit`} />
        </AppLayout>
    );
}
