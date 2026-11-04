import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Check, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import type { Option } from './category-form';

interface Row {
    id: string; name: string; image_url: string | null;
    category_type: string | null; category_type_label: string | null;
    require_acceptance: boolean; checkin_email: boolean;
}

const flag = (on: boolean) => (on ? <Check className="h-4 w-4" aria-label="Yes" /> : <span className="text-muted-foreground">—</span>);

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
    { accessorKey: 'category_type', header: 'Type', cell: ({ row }) => row.original.category_type_label ? <Badge variant="secondary">{row.original.category_type_label}</Badge> : '—' },
    { accessorKey: 'require_acceptance', header: 'Acceptance', cell: ({ row }) => flag(row.original.require_acceptance) },
    { accessorKey: 'checkin_email', header: 'Check-in email', cell: ({ row }) => flag(row.original.checkin_email) },
    {
        id: 'actions', header: '', size: 96,
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/categories/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/categories/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function CategoriesIndex({ categories, typeOptions }: { categories: { data: Row[]; meta: PaginationMeta }; typeOptions: Option[] }) {
    return (
        <AppLayout title="Categories" breadcrumbs={[{ label: 'Categories' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Categories</h1>
                <Button asChild><Link href="/app/categories/create">New category</Link></Button>
            </div>
            <DataTable columns={columns} rows={categories.data} pagination={categories.meta} baseUrl="/app/categories"
                sortable={['name', 'category_type']} filters={[{ key: 'category_type', label: 'Types', options: typeOptions }]}
                rowHref={(r) => `/app/categories/${r.id}/edit`} />
        </AppLayout>
    );
}
