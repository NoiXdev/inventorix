import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

// Don't hijack clicks that land on the row's own links.
const isInteractive = (t: EventTarget | null): boolean => t instanceof Element && !!t.closest('a, button');

function openRow(href: string, newTab: boolean, e: React.MouseEvent): void {
    if (isInteractive(e.target)) return;
    if (newTab || e.metaKey || e.ctrlKey) { window.open(href, '_blank', 'noopener'); return; }
    router.visit(href);
}

interface Doc { id: string; title: string; category_label: string | null; attached_to: string; uploaded_by: string | null; created_at: string | null; url: string; }
interface OpenIncident { id: number; title: string; model: string | null; serial: string | null; open_date: string | null; days_open: number | null; asset_url: string | null; }
interface Warranty { id: string; owner: string | null; model: string | null; serial: string | null; guarantee_end: string | null; days_left: number | null; asset_url: string; }

interface Props {
    stats: { assets: number };
    warranty: { expired: number; soon_30: number; soon_90: number };
    latestDocuments: Doc[];
    openIncidents: OpenIncident[];
    warrantyExpiring: Warranty[];
}

function StatCard({ label, value, tone }: { label: string; value: number; tone?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{label}</CardTitle></CardHeader>
            <CardContent className={cn('text-3xl font-semibold', tone)}>{value}</CardContent>
        </Card>
    );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">{title}</CardTitle></CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function Dashboard({ stats, warranty, latestDocuments, openIncidents, warrantyExpiring }: Props) {
    return (
        <AppLayout title="Dashboard" breadcrumbs={[{ label: 'Dashboard' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Dashboard</h1>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Assets" value={stats.assets} />
                <StatCard label="Warranty expired" value={warranty.expired} tone="text-red-600 dark:text-red-400" />
                <StatCard label="Expiring ≤ 30 days" value={warranty.soon_30} tone="text-amber-600 dark:text-amber-400" />
                <StatCard label="Expiring ≤ 90 days" value={warranty.soon_90} tone="text-blue-600 dark:text-blue-400" />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel title="Latest documents">
                    {latestDocuments.length === 0 ? <p className="text-sm text-muted-foreground">No documents.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Category</TableHead><TableHead>Attached to</TableHead><TableHead>Uploaded by</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {latestDocuments.map((d) => (
                                    <TableRow key={d.id} className="cursor-pointer" onClick={(e) => openRow(d.url, true, e)}>
                                        <TableCell><a href={d.url} target="_blank" rel="noreferrer" className="hover:underline">{d.title}</a></TableCell>
                                        <TableCell>{d.category_label ?? '—'}</TableCell>
                                        <TableCell>{d.attached_to ?? '—'}</TableCell>
                                        <TableCell>{d.uploaded_by ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>

                <Panel title="Open incidents">
                    {openIncidents.length === 0 ? <p className="text-sm text-muted-foreground">No open incidents.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>Days open</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {openIncidents.map((i) => (
                                    <TableRow key={i.id} className={cn(i.asset_url && 'cursor-pointer')} onClick={i.asset_url ? (e) => openRow(i.asset_url!, false, e) : undefined}>
                                        <TableCell>{i.asset_url ? <a href={i.asset_url} className="hover:underline">{i.title}</a> : i.title}</TableCell>
                                        <TableCell>{i.model ?? '—'}</TableCell>
                                        <TableCell>{i.serial ?? '—'}</TableCell>
                                        <TableCell>{i.days_open ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>

                <Panel title="Warranty expiring">
                    {warrantyExpiring.length === 0 ? <p className="text-sm text-muted-foreground">No upcoming expirations.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Owner</TableHead><TableHead>Model</TableHead><TableHead>Guarantee end</TableHead><TableHead>Days left</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {warrantyExpiring.map((w) => (
                                    <TableRow key={w.id} className="cursor-pointer" onClick={(e) => openRow(w.asset_url, false, e)}>
                                        <TableCell><a href={w.asset_url} className="hover:underline">{w.owner ?? '—'}</a></TableCell>
                                        <TableCell>{w.model ?? '—'}</TableCell>
                                        <TableCell>{w.guarantee_end ?? '—'}</TableCell>
                                        <TableCell className={cn(w.days_left !== null && w.days_left < 0 && 'text-red-600 dark:text-red-400')}>{w.days_left ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
