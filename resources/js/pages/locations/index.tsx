import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { Option } from './location-form';

interface Row {
    id: string; name: string | null; image_url: string | null; parent_path: string | null;
    city: string | null; country: string | null; country_name: string | null;
    manager_name: string | null; assets_count: number;
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
                <span>{row.original.name ?? '—'}</span>
            </div>
        ),
    },
    { id: 'parent', header: 'Parent', cell: ({ row }) => row.original.parent_path ?? dash },
    { accessorKey: 'city', header: 'City', cell: ({ row }) => row.original.city ?? dash },
    { accessorKey: 'country', header: 'Country', cell: ({ row }) => row.original.country_name ?? dash },
    { id: 'manager', header: 'Manager', cell: ({ row }) => row.original.manager_name ?? dash },
    { accessorKey: 'assets_count', header: 'Assets', cell: ({ row }) => row.original.assets_count },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/locations/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => {
                    const n = row.original.assets_count;
                    const warning = n > 0 ? `\n\n${n} asset${n === 1 ? '' : 's'} will keep pointing at it.` : '';
                    if (confirm(`Delete ${row.original.name ?? 'this location'}?${warning}`)) router.delete(`/app/locations/${row.original.id}`);
                }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

interface Props {
    locations: { data: Row[]; meta: PaginationMeta };
    countryOptions: Option[];
    parentOptions: Option[];
}

export default function LocationsIndex({ locations, countryOptions, parentOptions }: Props) {
    const filters = [
        ...(parentOptions.length > 0 ? [{ key: 'parent_id', label: 'Parent', options: parentOptions }] : []),
        ...(countryOptions.length > 0 ? [{ key: 'country', label: 'Countries', options: countryOptions }] : []),
    ];

    return (
        <AppLayout title="Locations" breadcrumbs={[{ label: 'Locations' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Locations</h1>
                <Button asChild><Link href="/app/locations/create">New location</Link></Button>
            </div>
            <DataTable columns={columns} rows={locations.data} pagination={locations.meta} baseUrl="/app/locations"
                sortable={['name', 'city', 'country', 'assets_count']} filters={filters}
                rowHref={(r) => `/app/locations/${r.id}/edit`} />
        </AppLayout>
    );
}
