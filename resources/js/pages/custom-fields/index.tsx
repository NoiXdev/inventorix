import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Check, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { Option } from './custom-field-form';

interface Row { id: string; name: string; element: string; element_label: string | null; format: string; is_unique: boolean; fieldsets_count: number; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'element', header: 'Element', cell: ({ row }) => <Badge variant="secondary">{row.original.element_label}</Badge> },
    { accessorKey: 'format', header: 'Format' },
    { accessorKey: 'is_unique', header: 'Unique', cell: ({ row }) => (row.original.is_unique ? <Check className="h-4 w-4" aria-label="Yes" /> : <span className="text-muted-foreground">—</span>) },
    { accessorKey: 'fieldsets_count', header: 'Fieldsets', cell: ({ row }) => <Badge variant="secondary">{row.original.fieldsets_count}</Badge> },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/custom-fields/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/custom-fields/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function CustomFieldsIndex({ customFields, elementOptions }: { customFields: { data: Row[]; meta: PaginationMeta }; elementOptions: Option[] }) {
    return (
        <AppLayout title="Custom fields" breadcrumbs={[{ label: 'Custom fields' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Custom fields</h1>
                <Button asChild><Link href="/app/custom-fields/create">New custom field</Link></Button>
            </div>
            <DataTable columns={columns} rows={customFields.data} pagination={customFields.meta} baseUrl="/app/custom-fields"
                sortable={['name', 'element', 'fieldsets_count']} filters={[{ key: 'element', label: 'Elements', options: elementOptions }]}
                rowHref={(r) => `/app/custom-fields/${r.id}/edit`} />
        </AppLayout>
    );
}
