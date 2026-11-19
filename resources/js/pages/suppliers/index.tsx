import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { Option } from './supplier-form';

interface Row {
    id: string; name: string; image_url: string | null;
    city: string | null; country: string | null; country_name: string | null;
    contact: string | null; email: string | null; phone: string | null; url: string | null;
}

const dash = <span className="text-muted-foreground">—</span>;

const columns: ColumnDef<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                {row.original.image_url
                    ? <img src={row.original.image_url} alt="" className="h-6 w-6 rounded border object-contain" />
                    : <span className="h-6 w-6 rounded border bg-muted" aria-hidden="true" />}
                <span>{row.original.name}</span>
            </div>
        ),
    },
    { accessorKey: 'city', header: 'City', cell: ({ row }) => row.original.city ?? dash },
    { accessorKey: 'country', header: 'Country', cell: ({ row }) => row.original.country_name ?? dash },
    { accessorKey: 'contact', header: 'Contact', cell: ({ row }) => row.original.contact ?? dash },
    {
        accessorKey: 'email', header: 'Email',
        cell: ({ row }) => row.original.email
            ? <a href={`mailto:${row.original.email}`} className="underline-offset-4 hover:underline">{row.original.email}</a>
            : dash,
    },
    {
        accessorKey: 'phone', header: 'Phone',
        cell: ({ row }) => row.original.phone
            ? <a href={`tel:${row.original.phone}`} className="underline-offset-4 hover:underline">{row.original.phone}</a>
            : dash,
    },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/suppliers/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/suppliers/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function SuppliersIndex({ suppliers, countryOptions }: { suppliers: { data: Row[]; meta: PaginationMeta }; countryOptions: Option[] }) {
    return (
        <AppLayout title="Suppliers" breadcrumbs={[{ label: 'Suppliers' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Suppliers</h1>
                <Button asChild><Link href="/app/suppliers/create">New supplier</Link></Button>
            </div>
            <DataTable columns={columns} rows={suppliers.data} pagination={suppliers.meta} baseUrl="/app/suppliers"
                sortable={['name', 'city', 'country', 'contact']}
                filters={countryOptions.length > 0 ? [{ key: 'country', label: 'Countries', options: countryOptions }] : []}
                rowHref={(r) => `/app/suppliers/${r.id}/edit`} />
        </AppLayout>
    );
}
