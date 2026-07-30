import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; firstname: string; lastname: string; email: string | null; assets_count: number }

const columns: ColumnDef<Row>[] = [
    { id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name },
    { id: 'email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/people/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                <Button asChild variant="ghost" size="icon"><Link href={`/app/people/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm('Delete this person?')) router.delete(`/app/people/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function PeopleIndex({ people }: { people: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="People" breadcrumbs={[{ label: 'People' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">People</h1>
                <Button asChild><Link href="/app/people/create">New person</Link></Button>
            </div>
            <DataTable
                columns={columns}
                rows={people.data}
                pagination={people.meta}
                baseUrl="/app/people"
                sortable={['name', 'email', 'assets_count']}
                rowHref={(r) => `/app/people/${r.id}`}
            />
        </AppLayout>
    );
}
