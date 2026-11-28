import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Can } from '@/components/can';
import { useCan } from '@/hooks/use-can';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { Option } from './department-form';

interface Row {
    id: string; name: string; manager_name: string | null; location_name: string | null; users_count: number;
}

const dash = <span className="text-muted-foreground">—</span>;

/** The users count, linking to the users list filtered to the department (as the user page does). */
function UsersCount({ row }: { row: Row }) {
    const can = useCan();
    if (!can('users.view')) return <>{row.users_count}</>;
    return <Link className="underline" href={`/app/users?filter[department_id]=${row.id}`}>{row.users_count}</Link>;
}

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { id: 'manager_name', header: 'Manager', cell: ({ row }) => row.original.manager_name ?? dash },
    { id: 'location_name', header: 'Location', cell: ({ row }) => row.original.location_name ?? dash },
    { accessorKey: 'users_count', header: 'Users', cell: ({ row }) => <UsersCount row={row.original} /> },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Can permission="departments.edit"><Button asChild variant="ghost" size="icon"><Link href={`/app/departments/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button></Can>
                <Can permission="departments.delete"><Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/departments/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button></Can>
            </div>
        ),
    },
];

export default function DepartmentsIndex({ departments, locationOptions }: { departments: { data: Row[]; meta: PaginationMeta }; locationOptions: Option[] }) {
    const can = useCan();
    return (
        <AppLayout title="Departments" breadcrumbs={[{ label: 'Departments' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Departments</h1>
                <Can permission="departments.create"><Button asChild><Link href="/app/departments/create">New department</Link></Button></Can>
            </div>
            <DataTable columns={columns} rows={departments.data} pagination={departments.meta} baseUrl="/app/departments"
                sortable={['name', 'users_count']}
                filters={locationOptions.length > 0 ? [{ key: 'location_id', label: 'Locations', options: locationOptions }] : []}
                rowHref={can('departments.edit') ? (r) => `/app/departments/${r.id}/edit` : undefined} />
        </AppLayout>
    );
}
