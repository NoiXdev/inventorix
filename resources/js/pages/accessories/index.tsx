import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { AccessoryOptions } from './accessory-form';

interface Row {
    id: string; name: string | null; image_url: string | null;
    category_name: string | null; manufacturer_name: string | null; model_number: string | null;
    location_name: string | null; qty: number; min_amt: number | null; low_stock: boolean;
    default_purchase_cost: string | null; requestable: boolean; archived: boolean;
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
    { id: 'location', header: 'Location', cell: ({ row }) => row.original.location_name ?? dash },
    {
        accessorKey: 'qty', header: 'Qty',
        cell: ({ row }) => (
            <span className={row.original.low_stock ? 'inline-flex items-center gap-1 font-medium text-destructive' : undefined}
                title={row.original.low_stock ? `At or below the minimum of ${row.original.min_amt}` : undefined}>
                {row.original.low_stock && <AlertTriangle className="h-4 w-4" aria-label="Low stock" />}
                {row.original.qty}
            </span>
        ),
    },
    { id: 'min_amt', header: 'Min', cell: ({ row }) => row.original.min_amt ?? dash },
    {
        accessorKey: 'default_purchase_cost', header: 'Cost',
        cell: ({ row }) => row.original.default_purchase_cost === null ? dash : money.format(Number(row.original.default_purchase_cost)),
    },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/accessories/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? 'this accessory'}?`)) router.delete(`/app/accessories/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

const ARCHIVED_OPTIONS = [{ value: '0', label: 'Active' }, { value: '1', label: 'Archived' }];

export default function AccessoriesIndex({ accessories, ...options }: AccessoryOptions & { accessories: { data: Row[]; meta: PaginationMeta } }) {
    const filters = [
        { key: 'category_id', label: 'Category', options: options.categoryOptions },
        { key: 'manufacturer_id', label: 'Manufacturer', options: options.manufacturerOptions },
        { key: 'location_id', label: 'Location', options: options.locationOptions },
        { key: 'archived', label: 'Status', options: ARCHIVED_OPTIONS },
    ].filter((f) => f.options.length > 0);

    return (
        <AppLayout title="Accessories" breadcrumbs={[{ label: 'Accessories' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Accessories</h1>
                <Button asChild><Link href="/app/accessories/create">New accessory</Link></Button>
            </div>
            <DataTable columns={columns} rows={accessories.data} pagination={accessories.meta} baseUrl="/app/accessories"
                sortable={['name', 'model_number', 'qty', 'default_purchase_cost']} filters={filters}
                rowHref={(r) => `/app/accessories/${r.id}/edit`} />
        </AppLayout>
    );
}
