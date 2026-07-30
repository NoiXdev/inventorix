import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string | null; email: string | null; login_enabled: boolean; assets_count: number; }

const columns: ColumnDef<Row>[] = [
    { id: 'person_name', accessorFn: (r) => r.name, header: 'Person', cell: ({ row }) => row.original.name ?? '—' },
    { id: 'users.email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { id: 'users.login_enabled', accessorFn: (r) => r.login_enabled, header: 'Login', cell: ({ row }) => (
        <Badge variant={row.original.login_enabled ? 'default' : 'secondary'}>{row.original.login_enabled ? 'Enabled' : 'Disabled'}</Badge>
    ) },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/users/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? row.original.email}?`)) router.delete(`/app/users/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function UsersIndex({ users }: { users: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Users" breadcrumbs={[{ label: 'Users' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Users</h1>
                <Button asChild><Link href="/app/users/create">New user</Link></Button>
            </div>
            <DataTable columns={columns} rows={users.data} pagination={users.meta} baseUrl="/app/users"
                sortable={['person_name', 'users.email', 'users.login_enabled', 'assets_count']}
                rowHref={(r) => `/app/users/${r.id}/edit`} />
        </AppLayout>
    );
}
