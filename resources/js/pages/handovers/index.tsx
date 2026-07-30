import { Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, FileDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row {
    id: string; type: string; type_label: string | null; recipient_name: string;
    recipient_kind_label: string | null; assets_count: number; created_by_name: string | null;
    signed_at: string | null; pdf_ready: boolean; pdf_url: string | null;
}

const columns: ColumnDef<Row>[] = [
    { id: 'signed_at', accessorFn: (r) => r.signed_at, header: 'Signed at', cell: ({ row }) => row.original.signed_at ?? '—' },
    { id: 'type', accessorFn: (r) => r.type, header: 'Type', cell: ({ row }) => <Badge variant="secondary">{row.original.type_label ?? row.original.type}</Badge> },
    { id: 'recipient_name', accessorFn: (r) => r.recipient_name, header: 'Recipient',
      cell: ({ row }) => <div><div>{row.original.recipient_name}</div><div className="text-xs text-muted-foreground">{row.original.recipient_kind_label}</div></div> },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    { accessorKey: 'created_by_name', header: 'Created by', cell: ({ row }) => row.original.created_by_name ?? '—' },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/handovers/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                {row.original.pdf_ready && row.original.pdf_url && (
                    <Button asChild variant="ghost" size="icon"><a href={row.original.pdf_url}><FileDown className="h-4 w-4" /></a></Button>
                )}
            </div>
        ),
    },
];

export default function HandoversIndex({ handovers }: { handovers: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Handovers" breadcrumbs={[{ label: 'Handovers' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Handovers</h1>
                <Button asChild><Link href="/app/handovers/create">New handover</Link></Button>
            </div>
            <DataTable columns={columns} rows={handovers.data} pagination={handovers.meta} baseUrl="/app/handovers"
                sortable={['signed_at', 'type', 'recipient_name', 'assets_count']}
                rowHref={(r) => `/app/handovers/${r.id}`} />
        </AppLayout>
    );
}
