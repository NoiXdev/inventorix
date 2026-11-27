import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

type Row = { id: string; name: string; users_count: number; summary: string };

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'users_count', header: 'Users' },
    { accessorKey: 'summary', header: 'Permissions', enableSorting: false },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/groups/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}? Its members lose the permissions it grants.`)) router.delete(`/app/groups/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function GroupsIndex({ groups }: { groups: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Groups" breadcrumbs={[{ label: 'Settings', href: '/app/settings' }, { label: 'Groups' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Groups</h1>
                <Button asChild><Link href="/app/groups/create">New group</Link></Button>
            </div>
            <DataTable columns={columns} rows={groups.data} pagination={groups.meta} baseUrl="/app/groups"
                sortable={['name', 'users_count']} rowHref={(r) => `/app/groups/${r.id}/edit`} />
        </AppLayout>
    );
}
