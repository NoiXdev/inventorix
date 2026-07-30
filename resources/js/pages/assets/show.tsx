import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StateBadge } from '@/components/assets/state-badge';
import { AssetAttachments, type AttachmentItem } from '@/components/assets/asset-attachments';
import { AssetIncidents, type IncidentItem } from '@/components/assets/asset-incidents';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';

interface AssetDetail {
    id: string; state: string; state_label: string | null;
    asset_type_name: string | null; manufacturer_name: string | null; model_name: string | null;
    owner_name: string | null; place_name: string | null; serial_number: string | null;
    buy_date: string | null; guarantee_end: string | null; buy_type_label: string | null;
    buy_price: number | null; invoice: string | null; tags: string[];
    incidentsCount: number; created_at: string | null; updated_at: string | null;
}

interface Props {
    asset: AssetDetail;
    attachments: AttachmentItem[];
    attachmentCategoryOptions: { value: string; label: string }[];
    incidents: IncidentItem[];
    history: HistoryEntry[];
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

export default function ShowAsset({ asset, attachments, attachmentCategoryOptions, incidents, history }: Props) {
    return (
        <AppLayout title="Asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: asset.serial_number ?? 'Asset' }]}>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{asset.model_name ?? 'Asset'}</h1>
                    <StateBadge state={asset.state} label={asset.state_label} />
                </div>
                <Button asChild><Link href={`/app/assets/${asset.id}/edit`}>Edit</Link></Button>
            </div>

            <Tabs defaultValue="general">
                <TabsList>
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="attachments">Attachments</TabsTrigger>
                    <TabsTrigger value="incidents">Incidents ({asset.incidentsCount})</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="general" className="mt-4">
                    <Card>
                        <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                            <Field label="Asset type">{asset.asset_type_name ?? '—'}</Field>
                            <Field label="Manufacturer">{asset.manufacturer_name ?? '—'}</Field>
                            <Field label="Model">{asset.model_name ?? '—'}</Field>
                            <Field label="Owner">{asset.owner_name ?? '—'}</Field>
                            <Field label="Place">{asset.place_name ?? '—'}</Field>
                            <Field label="Serial number">{asset.serial_number ?? '—'}</Field>
                            <Field label="Buy date">{asset.buy_date ?? '—'}</Field>
                            <Field label="Guarantee end">{asset.guarantee_end ?? '—'}</Field>
                            <Field label="Buy type">{asset.buy_type_label ?? '—'}</Field>
                            <Field label="Buy price">{asset.buy_price ?? '—'}</Field>
                            <Field label="Invoice">{asset.invoice ?? '—'}</Field>
                            <Field label="Tags">
                                {asset.tags.length ? <div className="flex flex-wrap gap-1">{asset.tags.map((t) => <Badge key={t} variant="secondary">{t}</Badge>)}</div> : '—'}
                            </Field>
                            <Field label="Created">{asset.created_at ?? '—'}</Field>
                            <Field label="Updated">{asset.updated_at ?? '—'}</Field>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="attachments" className="mt-4">
                    <AssetAttachments assetId={asset.id} attachments={attachments} categoryOptions={attachmentCategoryOptions} />
                </TabsContent>
                <TabsContent value="incidents" className="mt-4">
                    <AssetIncidents assetId={asset.id} incidents={incidents} />
                </TabsContent>
                <TabsContent value="history" className="mt-4">
                    <AssetHistory history={history} />
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}
