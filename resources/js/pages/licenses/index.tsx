import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

type Option = { value: string; label: string };

interface Row {
    id: string; name: string; category_name: string | null; manufacturer_name: string | null;
    licensed_to_name: string | null; seats: number; free_seats: number; low: boolean;
    expiration_date: string | null; expired: boolean;
}

const dash = <span className="text-muted-foreground">—</span>;

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name', cell: ({ row }) => row.original.name },
    { id: 'category', header: 'Category', cell: ({ row }) => row.original.category_name ?? dash },
    { id: 'manufacturer', header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? dash },
    { id: 'licensed_to', header: 'Licensed to', cell: ({ row }) => row.original.licensed_to_name ?? dash },
    {
        accessorKey: 'seats', header: 'Free / seats',
        cell: ({ row }) => (
            <span className={row.original.low ? 'inline-flex items-center gap-1 font-medium text-destructive' : undefined}>
                {row.original.low && <AlertTriangle className="h-4 w-4" aria-label="Few free seats" />}
                {row.original.free_seats} / {row.original.seats}
            </span>
        ),
    },
    {
        accessorKey: 'expiration_date', header: 'Expires',
        cell: ({ row }) => row.original.expiration_date
            ? <span className="flex items-center gap-2">{row.original.expiration_date}{row.original.expired && <Badge variant="destructive">Expired</Badge>}</span>
            : dash,
    },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Can permission="licenses.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/licenses/${row.original.id}/edit`} aria-label="Edit"><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="licenses.delete"><Button variant="ghost" size="icon" aria-label="Delete" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/licenses/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

interface Props {
    licenses: { data: Row[]; meta: PaginationMeta };
    categoryOptions: Option[];
    manufacturerOptions: Option[];
}

export default function LicensesIndex({ licenses, categoryOptions, manufacturerOptions }: Props) {
    const filters = [
        { key: 'category_id', label: 'Category', options: categoryOptions },
        { key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions },
    ].filter((f) => f.options.length > 0);

    return (
        <AppLayout title="Licenses" breadcrumbs={[{ label: 'Licenses' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Licenses</h1>
                <Can permission="licenses.create"><Button asChild><Link href="/app/licenses/create">New license</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={licenses.data} pagination={licenses.meta} baseUrl="/app/licenses"
                sortable={['name', 'seats', 'expiration_date']} filters={filters}
                rowHref={(r) => `/app/licenses/${r.id}`} />
        </AppLayout>
    );
}
