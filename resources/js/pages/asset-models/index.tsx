import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; manufacturer_id: string; manufacturer_name: string | null; assets_count: number; }
type Option = { value: string; label: string };

const columns: ColumnDef<Row>[] = [
    { id: 'asset_models.name', accessorFn: (r) => r.name, header: 'Name' },
    { id: 'manufacturer_name', accessorFn: (r) => r.manufacturer_name, header: 'Manufacturer',
      cell: ({ row }) => row.original.manufacturer_name ?? '—' },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets',
      cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/asset-models/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/asset-models/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

interface Props { assetModels: { data: Row[]; meta: PaginationMeta }; manufacturerOptions: Option[]; }

export default function AssetModelsIndex({ assetModels, manufacturerOptions }: Props) {
    return (
        <AppLayout title="Asset models" breadcrumbs={[{ label: 'Asset models' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Asset models</h1>
                <Button asChild><Link href="/app/asset-models/create">New asset model</Link></Button>
            </div>
            <DataTable
                columns={columns}
                rows={assetModels.data}
                pagination={assetModels.meta}
                baseUrl="/app/asset-models"
                rowHref={(r) => `/app/asset-models/${r.id}/edit`}
                sortable={['asset_models.name', 'manufacturer_name', 'assets_count']}
                filters={[{ key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions }]}
            />
        </AppLayout>
    );
}
