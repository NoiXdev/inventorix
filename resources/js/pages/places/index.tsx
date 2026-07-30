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
                <Button asChild variant="ghost" size="icon"><Link href={`/app/places/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/places/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function PlacesIndex({ places }: { places: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Places" breadcrumbs={[{ label: 'Places' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Places</h1>
                <Button asChild><Link href="/app/places/create">New place</Link></Button>
            </div>
            <DataTable columns={columns} rows={places.data} pagination={places.meta} baseUrl="/app/places" sortable={['name']} rowHref={(r) => `/app/places/${r.id}/edit`} />
        </AppLayout>
    );
}
