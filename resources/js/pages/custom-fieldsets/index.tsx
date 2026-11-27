import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { useCan } from '@/hooks/use-can';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; fields_count: number; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'fields_count', header: 'Fields', cell: ({ row }) => <Badge variant="secondary">{row.original.fields_count}</Badge> },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Can permission="customfields.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/custom-fieldsets/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="customfields.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/custom-fieldsets/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

export default function CustomFieldsetsIndex({ customFieldsets }: { customFieldsets: { data: Row[]; meta: PaginationMeta } }) {
    const can = useCan();
    return (
        <AppLayout title="Fieldsets" breadcrumbs={[{ label: 'Fieldsets' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Fieldsets</h1>
                <Can permission="customfields.create"><Button asChild><Link href="/app/custom-fieldsets/create">New fieldset</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={customFieldsets.data} pagination={customFieldsets.meta} baseUrl="/app/custom-fieldsets"
                sortable={['name', 'fields_count']} rowHref={can('customfields.edit') ? (r) => `/app/custom-fieldsets/${r.id}/edit` : undefined} />
        </AppLayout>
    );
}
