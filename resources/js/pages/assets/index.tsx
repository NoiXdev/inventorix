import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, QrCode, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import { StateBadge } from '@/components/assets/state-badge';
import { AssetImportDialog } from '@/components/assets/asset-import-dialog';
import { Card, CardContent } from '@/components/ui/card';
import { QrPrintModal } from '@/components/qr-print/qr-print-modal';
import type { LabelItem } from '@/plugins/qr-print/types';

interface Row {
    id: string; state: string; state_label: string | null;
    asset_type_name: string | null; manufacturer_name: string | null; model_name: string | null;
    owner_name: string | null; serial_number: string | null; buy_price: number | null;
    created_at: string | null; incidents_count: number;
}
type Option = { value: string; label: string };

const itemFor = (r: Row): LabelItem => ({
    uuid: r.id,
    ...(r.model_name
        ? {
              metadata: {
                  modelName: r.model_name,
                  ...(r.serial_number ? { serial: r.serial_number } : {}),
                  ...(r.manufacturer_name ? { manufacturer: r.manufacturer_name } : {}),
                  ...(r.created_at ? { createdAt: r.created_at } : {}),
              },
          }
        : {}),
});

interface Props {
    assets: { data: Row[]; meta: PaginationMeta };
    stateOptions: Option[]; assetTypeOptions: Option[]; manufacturerOptions: Option[];
    importResult?: { imported: number; failed: { row: number; message: string }[] } | null;
}

export default function AssetsIndex({ assets, stateOptions, assetTypeOptions, manufacturerOptions, importResult }: Props) {
    const [printItems, setPrintItems] = useState<LabelItem[] | null>(null);

    const columns: ColumnDef<Row>[] = [
        { id: 'assets.state', accessorFn: (r) => r.state, header: 'State', cell: ({ row }) => <StateBadge state={row.original.state} label={row.original.state_label} /> },
        { id: 'asset_type_name', accessorFn: (r) => r.asset_type_name, header: 'Type', cell: ({ row }) => row.original.asset_type_name ?? '—' },
        { id: 'manufacturer_name', accessorFn: (r) => r.manufacturer_name, header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? '—' },
        { id: 'model_name', accessorFn: (r) => r.model_name, header: 'Model', cell: ({ row }) => row.original.model_name ?? '—' },
        { id: 'owner_name', accessorFn: (r) => r.owner_name, header: 'Owner', cell: ({ row }) => row.original.owner_name ?? '—' },
        { id: 'assets.serial_number', accessorFn: (r) => r.serial_number, header: 'Serial', cell: ({ row }) => row.original.serial_number ?? '—' },
        { id: 'assets.buy_price', accessorFn: (r) => r.buy_price, header: 'Buy price', cell: ({ row }) => row.original.buy_price ?? '—' },
        { id: 'incidents_count', accessorFn: (r) => r.incidents_count, header: 'Incidents', cell: ({ row }) => <Badge variant="secondary">{row.original.incidents_count}</Badge> },
        {
            id: 'actions', header: '',
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
                sortable={['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count']}
                filters={[
                    { key: 'state', label: 'State', options: stateOptions },
                    { key: 'asset_type_id', label: 'Type', options: assetTypeOptions },
                    { key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions },
                ]}
                enableSelection
                getRowId={(r) => r.id}
                renderBulkActions={(rows, clear) => (
                    <Button size="sm" onClick={() => { setPrintItems(rows.map(itemFor)); clear(); }}>QR drucken ({rows.length})</Button>
                )}
            />
            <QrPrintModal open={!!printItems} items={printItems ?? []} onClose={() => setPrintItems(null)} />
        </AppLayout>
    );
}
