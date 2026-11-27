import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, LogOut, Pencil, QrCode, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import { StatusBadge, type AssetStatus } from '@/components/assets/status-badge';
import { AssetImportDialog } from '@/components/assets/asset-import-dialog';
import { Card, CardContent } from '@/components/ui/card';
import { QrPrintModal } from '@/components/qr-print/qr-print-modal';
import type { LabelItem } from '@/plugins/qr-print/types';

interface Row {
    id: string; name: string | null; asset_tag: string | null; status: AssetStatus | null;
    category_name: string | null; manufacturer_name: string | null; model_name: string | null;
    assignee_name: string | null; serial: string | null; purchase_cost: string | null; archived: boolean;
    created_at: string | null; incidents_count: number;
    custom: Record<string, string | null>;
}
type Option = { value: string; label: string };

const itemFor = (r: Row): LabelItem => ({
    uuid: r.id,
    ...(r.model_name
        ? {
              metadata: {
                  modelName: r.model_name,
                  ...(r.serial ? { serial: r.serial } : {}),
                  ...(r.manufacturer_name ? { manufacturer: r.manufacturer_name } : {}),
                  ...(r.created_at ? { createdAt: r.created_at } : {}),
              },
          }
        : {}),
});

interface Props {
    assets: { data: Row[]; meta: PaginationMeta };
    statusOptions: Option[]; categoryOptions: Option[]; manufacturerOptions: Option[]; locationOptions: Option[];
    /** Custom fields flagged show_in_listview. */
    customColumns?: { key: string; label: string }[];
    importResult?: { imported: number; failed: { row: number; message: string }[] } | null;
}

export default function AssetsIndex({ assets, statusOptions, categoryOptions, manufacturerOptions, locationOptions, customColumns = [], importResult }: Props) {
    const [printItems, setPrintItems] = useState<LabelItem[] | null>(null);

    const columns: ColumnDef<Row>[] = [
        {
            id: 'assets.name', accessorFn: (r) => r.name, header: 'Name',
            cell: ({ row }) => (
                <span className="flex items-center gap-2">
                    {row.original.name ?? '—'}
                    {row.original.archived && <Badge variant="outline">Archived</Badge>}
                </span>
            ),
        },
        { id: 'assets.asset_tag', accessorFn: (r) => r.asset_tag, header: 'Asset tag', cell: ({ row }) => row.original.asset_tag ?? '—' },
        { id: 'status_name', accessorFn: (r) => r.status?.name, header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
        { id: 'category_name', accessorFn: (r) => r.category_name, header: 'Category', cell: ({ row }) => row.original.category_name ?? '—' },
        { id: 'manufacturer_name', accessorFn: (r) => r.manufacturer_name, header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? '—' },
        { id: 'model_name', accessorFn: (r) => r.model_name, header: 'Model', cell: ({ row }) => row.original.model_name ?? '—' },
        { id: 'assignee_name', accessorFn: (r) => r.assignee_name, header: 'Assigned to', cell: ({ row }) => row.original.assignee_name ?? '—' },
        { id: 'assets.serial', accessorFn: (r) => r.serial, header: 'Serial', cell: ({ row }) => row.original.serial ?? '—' },
        { id: 'assets.purchase_cost', accessorFn: (r) => r.purchase_cost, header: 'Purchase cost', cell: ({ row }) => row.original.purchase_cost ?? '—' },
        { id: 'incidents_count', accessorFn: (r) => r.incidents_count, header: 'Incidents', cell: ({ row }) => <Badge variant="secondary">{row.original.incidents_count}</Badge> },
        ...customColumns.map((c): ColumnDef<Row> => ({
            id: `assets.${c.key}`, accessorFn: (r) => r.custom?.[c.key], header: c.label,
            cell: ({ row }) => row.original.custom?.[c.key] ?? '—',
        })),
        {
            id: 'actions', header: '', size: 176,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setPrintItems([itemFor(row.original)])} aria-label="Print QR"><QrCode className="h-4 w-4" /></Button>
                    <Button asChild variant="ghost" size="icon"><Link href={`/app/assets/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                    <Button asChild variant="ghost" size="icon"><Link href={`/app/assets/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                    <Button variant="ghost" size="icon" onClick={() => { if (confirm('Delete this asset?')) router.delete(`/app/assets/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Assets" breadcrumbs={[{ label: 'Assets' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Assets</h1>
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <a href={`/app/assets/export${typeof window !== 'undefined' ? window.location.search : ''}`}>Export</a>
                    </Button>
                    <AssetImportDialog />
                    <Button variant="outline" asChild>
                        <Link href="/app/assets/bulk-checkout"><LogOut className="h-4 w-4" /> Bulk checkout</Link>
                    </Button>
                    <Button asChild><Link href="/app/assets/create">New asset</Link></Button>
                </div>
            </div>
            {importResult && (
                <Card className="mb-4">
                    <CardContent className="py-4 text-sm">
                        <div className="font-medium">Imported {importResult.imported}{importResult.failed.length ? `, ${importResult.failed.length} failed` : ''}.</div>
                        {importResult.failed.length > 0 && (
                            <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-muted-foreground">
                                {importResult.failed.map((f) => <li key={f.row}>Row {f.row}: {f.message}</li>)}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            )}
            <DataTable
                columns={columns}
                rows={assets.data}
                pagination={assets.meta}
                baseUrl="/app/assets"
                rowHref={(r) => `/app/assets/${r.id}`}
                sortable={['assets.name', 'assets.asset_tag', 'status_name', 'category_name', 'manufacturer_name', 'model_name', 'assignee_name', 'assets.serial', 'assets.purchase_cost', 'incidents_count', ...customColumns.map((c) => `assets.${c.key}`)]}
                filters={[
                    { key: 'status_id', label: 'Status', options: statusOptions },
                    { key: 'category_id', label: 'Category', options: categoryOptions },
                    { key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions },
                    { key: 'location_id', label: 'Location', options: locationOptions },
                    { key: 'archived', label: 'Archived', options: [{ value: '0', label: 'Active' }, { value: '1', label: 'Archived' }] },
                ]}
                enableSelection
                getRowId={(r) => r.id}
                renderBulkActions={(rows, clear) => (
                    <>
                        <Button size="sm" variant="outline" onClick={() => router.get('/app/assets/bulk-checkout', { ids: rows.map((r) => r.id).join(',') })}>
                            <LogOut className="h-4 w-4" /> Check out ({rows.length})
                        </Button>
                        <Button size="sm" onClick={() => { setPrintItems(rows.map(itemFor)); clear(); }}>QR drucken ({rows.length})</Button>
                    </>
                )}
            />
            <QrPrintModal open={!!printItems} items={printItems ?? []} onClose={() => setPrintItems(null)} />
        </AppLayout>
    );
}
