import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, KeyRound, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { useCan } from '@/hooks/use-can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Option { value: string; label: string }
interface Row {
    id: string; name: string | null; email: string | null; login_enabled: boolean; assets_count: number;
    employee_num?: string | null; jobtitle?: string | null; department_name?: string | null; location_name?: string | null;
}

const columns: ColumnDef<Row>[] = [
    { id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name ?? '—' },
    { id: 'email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { id: 'employee_num', accessorFn: (r) => r.employee_num, header: 'Employee no.', cell: ({ row }) => row.original.employee_num ?? '—' },
    { id: 'jobtitle', accessorFn: (r) => r.jobtitle, header: 'Job title', cell: ({ row }) => row.original.jobtitle ?? '—' },
    { id: 'department_name', accessorFn: (r) => r.department_name, header: 'Department', cell: ({ row }) => row.original.department_name ?? '—' },
    { id: 'location_name', accessorFn: (r) => r.location_name, header: 'Location', cell: ({ row }) => row.original.location_name ?? '—' },
    { id: 'login_enabled', accessorFn: (r) => r.login_enabled, header: 'Login', cell: ({ row }) => (
        <Badge variant={row.original.login_enabled ? 'default' : 'secondary'}>{row.original.login_enabled ? 'Enabled' : 'Disabled'}</Badge>
    ) },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '', size: 136,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/users/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                <Can permission="users.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/users/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="users.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? row.original.email}?`)) router.delete(`/app/users/${row.original.id}`, { onError: (errors) => toast.error(errors.user ?? 'Could not delete the user.') }); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

interface IndexProps {
    users: { data: Row[]; meta: PaginationMeta };
    departmentOptions?: Option[]; locationOptions?: Option[]; managerOptions?: Option[];
}

export default function UsersIndex({ users, departmentOptions = [], locationOptions = [], managerOptions = [] }: IndexProps) {
    const can = useCan();
    const filters = [
        { key: 'department_id', label: 'Department', options: departmentOptions },
        { key: 'location_id', label: 'Location', options: locationOptions },
        { key: 'manager_id', label: 'Manager', options: managerOptions },
    ].filter((f) => f.options.length > 0);

    return (
        <AppLayout title="Users" breadcrumbs={[{ label: 'Users' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Users</h1>
                <Can permission="users.create"><Button asChild><Link href="/app/users/create">New user</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={users.data} pagination={users.meta} baseUrl="/app/users"
                sortable={['name', 'email', 'login_enabled', 'assets_count', 'employee_num', 'jobtitle']} filters={filters}
                rowHref={(r) => `/app/users/${r.id}`}
                enableSelection
                getRowId={(r) => r.id}
                renderBulkActions={(rows, clear) => can('users.edit') ? (
                    <Button size="sm" variant="outline" onClick={() => router.post('/app/users/bulk-enable-login', { ids: rows.map((r) => r.id) }, { preserveScroll: true, onSuccess: clear })}>
                        <KeyRound className="h-4 w-4" /> Enable login ({rows.length})
                    </Button>
                ) : null} />
        </AppLayout>
    );
}
