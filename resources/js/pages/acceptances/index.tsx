import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Send } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row {
    id: string; item_type: string; item_name: string; item_url: string | null;
    user_name: string | null; user_url: string | null; can_remind: boolean;
    status: 'pending' | 'accepted' | 'declined'; sent_at: string | null; answered_at: string | null; note: string | null;
}

const STATUS_BADGE: Record<Row['status'], { label: string; variant: 'secondary' | 'default' | 'destructive' }> = {
    pending: { label: 'Pending', variant: 'secondary' },
    accepted: { label: 'Accepted', variant: 'default' },
    declined: { label: 'Declined', variant: 'destructive' },
};
const TYPE_LABEL: Record<string, string> = { asset: 'Asset', accessory: 'Accessory', license: 'License', consumable: 'Consumable' };

const columns: ColumnDef<Row>[] = [
    {
        id: 'item', header: 'Item',
        cell: ({ row }) => (
            <span>
                <span className="text-xs text-muted-foreground">{TYPE_LABEL[row.original.item_type] ?? row.original.item_type}: </span>
                {row.original.item_url ? <Link href={row.original.item_url} className="hover:underline">{row.original.item_name}</Link> : row.original.item_name}
            </span>
        ),
    },
    { id: 'user', header: 'User', cell: ({ row }) => row.original.user_url ? <Link href={row.original.user_url} className="hover:underline">{row.original.user_name}</Link> : (row.original.user_name ?? '—') },
    { id: 'status', header: 'Status', cell: ({ row }) => <Badge variant={STATUS_BADGE[row.original.status].variant}>{STATUS_BADGE[row.original.status].label}</Badge> },
    { id: 'sent', header: 'Link sent', cell: ({ row }) => row.original.sent_at ?? '—' },
    { id: 'answered', header: 'Answered', cell: ({ row }) => row.original.answered_at ?? '—' },
    { id: 'note', header: 'Note', cell: ({ row }) => <span className="line-clamp-1 max-w-64">{row.original.note ?? ''}</span> },
    {
        id: 'actions', header: '', size: 140,
        cell: ({ row }) => row.original.can_remind ? (
            <div className="flex justify-end">
                <Button size="sm" variant="ghost" onClick={() => router.post(`/app/acceptances/${row.original.id}/remind`, {}, { preserveScroll: true })}>
                    <Send className="h-4 w-4" /> Send again
                </Button>
            </div>
        ) : null,
    },
];

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'declined', label: 'Declined' },
    { value: 'all', label: 'All' },
];

export default function AcceptancesIndex({ acceptances }: { acceptances: { data: Row[]; meta: PaginationMeta }; status: string }) {
    return (
        <AppLayout title="Acceptances" breadcrumbs={[{ label: 'Acceptances' }]}>
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Acceptances</h1>
                <p className="text-sm text-muted-foreground">Items from categories that require acceptance. Users answer through the link in their email, without logging in.</p>
            </div>
            <DataTable columns={columns} rows={acceptances.data} pagination={acceptances.meta} baseUrl="/app/acceptances"
                filters={[{ key: 'status', label: 'Status', options: STATUS_OPTIONS }]} />
        </AppLayout>
    );
}
