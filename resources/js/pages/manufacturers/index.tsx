import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; models_count: number; assets_count: number; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'models_count', header: 'Models', cell: ({ row }) => <Badge variant="secondary">{row.original.models_count}</Badge> },
    { accessorKey: 'assets_count', header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/manufacturers/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => {
                    if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/manufacturers/${row.original.id}`);
                }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function ManufacturersIndex({ manufacturers }: { manufacturers: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Manufacturers" breadcrumbs={[{ label: 'Manufacturers' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Manufacturers</h1>
                <Button asChild><Link href="/app/manufacturers/create">New manufacturer</Link></Button>
            </div>
            <DataTable columns={columns} rows={manufacturers.data} pagination={manufacturers.meta} baseUrl="/app/manufacturers" />
        </AppLayout>
    );
}
