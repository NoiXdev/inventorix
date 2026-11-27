import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatusBadge, type AssetStatus } from '@/components/assets/status-badge';
import { AssetAttachments, type AttachmentItem } from '@/components/assets/asset-attachments';
import { AssetIncidents, type IncidentItem } from '@/components/assets/asset-incidents';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';
import { useCan } from '@/hooks/use-can';
import { CheckoutDialogs, type CheckoutOptions } from '@/components/assets/checkout-dialogs';
import { AuditDialog, type AuditOptions } from '@/components/assets/audit-dialog';

interface AssetDetail {
    id: string; name: string | null; asset_tag: string | null; status: AssetStatus | null;
    category_name: string | null; manufacturer_name: string | null; model_name: string | null;
    assignee: { type: 'user' | 'location' | 'asset'; name: string; url: string | null } | null;
    location_name: string | null; rtd_location_name: string | null; supplier_name: string | null; serial: string | null;
    purchase_date: string | null; purchase_cost: string | null; order_number: string | null;
    warranty_months: number | null; warranty_end: string | null;
    asset_eol_date: string | null; eol_explicit: boolean; depreciate: boolean | null;
    physical: boolean; requestable: boolean; byod: boolean | null; archived: boolean;
    notes: string | null; image_url: string | null;
    last_checkout: string | null; last_checkin: string | null; expected_checkin: string | null;
    last_audit_date: string | null; next_audit_date: string | null;
    checkout_counter: number; checkin_counter: number; requests_counter: number;
    tags: string[]; incidentsCount?: number; created_at: string | null; updated_at: string | null;
}

const ASSIGNEE_LABELS = { user: 'User', location: 'Location', asset: 'Asset' } as const;
const yesNo = (v: boolean | null) => (v ? 'Yes' : 'No');

interface Props {
    asset: AssetDetail;
    attachments: AttachmentItem[];
    attachmentCategoryOptions: { value: string; label: string }[];
    /** Absent without incidents.view. */
    incidents?: IncidentItem[];
    history: HistoryEntry[];
    customFields: { name: string; value: string | null; encrypted: boolean }[];
    checkoutBlocker: string | null;
    checkoutOptions?: CheckoutOptions;
    auditOptions?: AuditOptions | null;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

export default function ShowAsset({ asset, attachments, attachmentCategoryOptions, incidents = [], history, customFields = [], checkoutBlocker, checkoutOptions, auditOptions }: Props) {
    const can = useCan();
    const canCheckout = asset.assignee !== null ? can('assets.checkin') : can('assets.checkout');
    const showIncidents = can('incidents.view');

    return (
        <AppLayout title="Asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: asset.name ?? asset.asset_tag ?? asset.serial ?? 'Asset' }]}>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{asset.name ?? asset.model_name ?? 'Asset'}</h1>
                    <StatusBadge status={asset.status} />
                    {asset.archived && <Badge variant="outline">Archived</Badge>}
                </div>
                <div className="flex gap-2">
                    {can('assets.audit') && <AuditDialog assetId={asset.id} locationName={asset.location_name} options={auditOptions} />}
                    {canCheckout && <CheckoutDialogs assetId={asset.id} checkedOut={asset.assignee !== null} blocker={checkoutBlocker} options={checkoutOptions} />}
                    {can('assets.edit') && <Button asChild><Link href={`/app/assets/${asset.id}/edit`}>Edit</Link></Button>}
                </div>
            </div>

            <Tabs defaultValue="general">
                <TabsList>
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="attachments">Attachments</TabsTrigger>
                    {showIncidents && <TabsTrigger value="incidents">Incidents ({asset.incidentsCount ?? incidents.length})</TabsTrigger>}
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="general" className="mt-4">
                    <Card>
                        <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                            {asset.image_url && (
                                <div className="col-span-2 md:col-span-3">
                                    <img src={asset.image_url} alt="" className="h-32 w-auto rounded-md border object-contain" />
                                </div>
                            )}
                            <Field label="Asset tag">{asset.asset_tag ?? '—'}</Field>
                            <Field label="Serial">{asset.serial ?? '—'}</Field>
                            <Field label="Category">{asset.category_name ?? '—'}</Field>
                            <Field label="Manufacturer">{asset.manufacturer_name ?? '—'}</Field>
                            <Field label="Model">{asset.model_name ?? '—'}</Field>
                            <Field label="Assigned to">
                                {asset.assignee ? (
                                    <span>
                                        <span className="text-muted-foreground">{ASSIGNEE_LABELS[asset.assignee.type]}: </span>
                                        {asset.assignee.url ? <Link href={asset.assignee.url} className="hover:underline">{asset.assignee.name}</Link> : asset.assignee.name}
                                    </span>
                                ) : '—'}
                            </Field>
                            <Field label="Location">{asset.location_name ?? '—'}</Field>
                            <Field label="Default location">{asset.rtd_location_name ?? '—'}</Field>
                            <Field label="Requestable">{yesNo(asset.requestable)}</Field>
                            <Field label="Tags">
                                {asset.tags.length ? <div className="flex flex-wrap gap-1">{asset.tags.map((t) => <Badge key={t} variant="secondary">{t}</Badge>)}</div> : '—'}
                            </Field>
                            <Field label="Created">{asset.created_at ?? '—'}</Field>
                            <Field label="Updated">{asset.updated_at ?? '—'}</Field>
                        </CardContent>
                    </Card>
                    <Card className="mt-4">
                        <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                            <Field label="Supplier">{asset.supplier_name ?? '—'}</Field>
                            <Field label="Order number">{asset.order_number ?? '—'}</Field>
                            <Field label="Purchase date">{asset.purchase_date ?? '—'}</Field>
                            <Field label="Purchase cost">{asset.purchase_cost ?? '—'}</Field>
                            <Field label="Warranty">
                                {asset.warranty_months != null ? `${asset.warranty_months} months${asset.warranty_end ? ` (until ${asset.warranty_end})` : ''}` : '—'}
                            </Field>
                            <Field label="Depreciate">{yesNo(asset.depreciate)}</Field>
                            <Field label="EOL date">{asset.asset_eol_date ? `${asset.asset_eol_date}${asset.eol_explicit ? ' (set by hand)' : ''}` : '—'}</Field>
                            <Field label="Last audit">{asset.last_audit_date ?? '—'}</Field>
                            <Field label="Next audit">{asset.next_audit_date ?? '—'}</Field>
                            <Field label="Physical">{yesNo(asset.physical)}</Field>
                            <Field label="BYOD">{yesNo(asset.byod)}</Field>
                        </CardContent>
                    </Card>
                    <Card className="mt-4">
                        <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                            <Field label="Last checkout">{asset.last_checkout ?? '—'}</Field>
                            <Field label="Last check-in">{asset.last_checkin ?? '—'}</Field>
                            <Field label="Expected check-in">{asset.expected_checkin ?? '—'}</Field>
                            <Field label="Checkouts">{asset.checkout_counter}</Field>
                            <Field label="Check-ins">{asset.checkin_counter}</Field>
                            <Field label="Requests">{asset.requests_counter}</Field>
                        </CardContent>
                    </Card>
                    {asset.notes && (
                        <Card className="mt-4">
                            <CardContent className="py-6">
                                <Field label="Notes"><span className="whitespace-pre-line">{asset.notes}</span></Field>
                            </CardContent>
                        </Card>
                    )}
                    {customFields.length > 0 && (
                        <Card className="mt-4">
                            <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                                {customFields.map((f) => (
                                    <Field key={f.name} label={f.name}>
                                        <span className="inline-flex items-center gap-1 whitespace-pre-line">
                                            {f.encrypted && <Lock className="h-3 w-3 text-muted-foreground" aria-label="Encrypted" />}
                                            {f.value ?? '—'}
                                        </span>
                                    </Field>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>

                <TabsContent value="attachments" className="mt-4">
                    <AssetAttachments assetId={asset.id} attachments={attachments} categoryOptions={attachmentCategoryOptions} />
                </TabsContent>
                {showIncidents && (
                    <TabsContent value="incidents" className="mt-4">
                        <AssetIncidents assetId={asset.id} incidents={incidents} />
                    </TabsContent>
                )}
                <TabsContent value="history" className="mt-4">
                    <AssetHistory history={history} />
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}
