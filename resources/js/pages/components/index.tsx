import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { ComponentOptions } from './component-form';

interface Row {
    id: string; name: string | null; image_url: string | null;
    category_name: string | null; manufacturer_name: string | null; model_number: string | null;
    location_name: string | null; serial: string | null; qty: number; remaining: number; min_amt: number | null; low_stock: boolean;
    purchase_cost: string | null;
}

const dash = <span className="text-muted-foreground">—</span>;
const money = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const columns: ColumnDef<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                {row.original.image_url
                    ? <img src={row.original.image_url} alt="" className="h-6 w-6 rounded border object-contain" />
                    : <span className="h-6 w-6 rounded border bg-muted" aria-hidden="true" />}
                <span>{row.original.name ?? '—'}</span>
            </div>
        ),
    },
    { id: 'category', header: 'Category', cell: ({ row }) => row.original.category_name ?? dash },
    { id: 'manufacturer', header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? dash },
    { accessorKey: 'model_number', header: 'Model no.', cell: ({ row }) => row.original.model_number ?? dash },
    { accessorKey: 'serial', header: 'Serial', cell: ({ row }) => row.original.serial ?? dash },
    { id: 'location', header: 'Location', cell: ({ row }) => row.original.location_name ?? dash },
    {
        accessorKey: 'qty', header: 'Available / qty',
        cell: ({ row }) => (
            <span className={row.original.low_stock ? 'inline-flex items-center gap-1 font-medium text-destructive' : undefined}
                title={row.original.low_stock ? `At or below the minimum of ${row.original.min_amt}` : undefined}>
                {row.original.low_stock && <AlertTriangle className="h-4 w-4" aria-label="Low stock" />}
                {row.original.remaining} / {row.original.qty}
            </span>
        ),
    },
    { id: 'min_amt', header: 'Min', cell: ({ row }) => row.original.min_amt ?? dash },
    {
        accessorKey: 'purchase_cost', header: 'Cost',
        cell: ({ row }) => row.original.purchase_cost === null ? dash : money.format(Number(row.original.purchase_cost)),
    },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Can permission="components.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/components/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="components.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? 'this component'}?`)) router.delete(`/app/components/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

export default function ComponentsIndex({ components, ...options }: ComponentOptions & { components: { data: Row[]; meta: PaginationMeta } }) {
    const filters = [
        { key: 'category_id', label: 'Category', options: options.categoryOptions },
        { key: 'manufacturer_id', label: 'Manufacturer', options: options.manufacturerOptions },
        { key: 'location_id', label: 'Location', options: options.locationOptions },
    ].filter((f) => f.options.length > 0);

    return (
        <AppLayout title="Components" breadcrumbs={[{ label: 'Components' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Components</h1>
                <Can permission="components.create"><Button asChild><Link href="/app/components/create">New component</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={components.data} pagination={components.meta} baseUrl="/app/components"
                sortable={['name', 'model_number', 'serial', 'qty', 'purchase_cost']} filters={filters}
                rowHref={(r) => `/app/components/${r.id}`} />
        </AppLayout>
    );
}
