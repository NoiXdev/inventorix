import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { ConsumableOptions } from './consumable-form';

interface Row {
    id: string; name: string | null; image_url: string | null;
    category_name: string | null; manufacturer_name: string | null; model_number: string | null;
    location_name: string | null; item_no: string | null; qty: number; remaining: number; min_amt: number | null; low_stock: boolean;
    purchase_cost: string | null; requestable: boolean; archived: boolean;
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
                {row.original.archived && <Badge variant="outline">Archived</Badge>}
            </div>
        ),
    },
    { id: 'category', header: 'Category', cell: ({ row }) => row.original.category_name ?? dash },
    { id: 'manufacturer', header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? dash },
    { accessorKey: 'model_number', header: 'Model no.', cell: ({ row }) => row.original.model_number ?? dash },
    { id: 'item_no', header: 'Item no.', cell: ({ row }) => row.original.item_no ?? dash },
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
                <Can permission="consumables.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/consumables/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="consumables.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? 'this consumable'}?`)) router.delete(`/app/consumables/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

const ARCHIVED_OPTIONS = [{ value: '0', label: 'Active' }, { value: '1', label: 'Archived' }];

export default function ConsumablesIndex({ consumables, ...options }: ConsumableOptions & { consumables: { data: Row[]; meta: PaginationMeta } }) {
    const filters = [
        { key: 'category_id', label: 'Category', options: options.categoryOptions },
        { key: 'manufacturer_id', label: 'Manufacturer', options: options.manufacturerOptions },
        { key: 'location_id', label: 'Location', options: options.locationOptions },
        { key: 'archived', label: 'Status', options: ARCHIVED_OPTIONS },
    ].filter((f) => f.options.length > 0);

    return (
        <AppLayout title="Consumables" breadcrumbs={[{ label: 'Consumables' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Consumables</h1>
                <Can permission="consumables.create"><Button asChild><Link href="/app/consumables/create">New consumable</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={consumables.data} pagination={consumables.meta} baseUrl="/app/consumables"
                sortable={['name', 'model_number', 'qty', 'purchase_cost']} filters={filters}
                rowHref={(r) => `/app/consumables/${r.id}`} />
        </AppLayout>
    );
}
