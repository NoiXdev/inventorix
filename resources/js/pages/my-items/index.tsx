import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Acceptance = 'pending' | 'accepted' | 'declined';
interface AssetRow { id: string; name: string | null; asset_tag: string | null; model: string | null; serial: string | null; checked_out_at: string | null; acceptance: Acceptance | null; href: string | null }
interface LicenseRow { name: string; checked_out_at: string | null; href: string | null }
interface QtyRow { name: string; qty: number; checked_out_at: string | null; href: string | null }
interface Pending { id: string; item_name: string; sent_at: string | null }
interface Props {
    assets: AssetRow[];
    licenses: LicenseRow[];
    accessories: QtyRow[];
    consumables: QtyRow[];
    pendingAcceptances: Pending[];
}

const ACCEPTANCE: Record<Acceptance, { label: string; variant: 'secondary' | 'default' | 'destructive' }> = {
    pending: { label: 'Pending', variant: 'secondary' },
    accepted: { label: 'Accepted', variant: 'default' },
    declined: { label: 'Declined', variant: 'destructive' },
};

function Name({ name, href }: { name: string | null; href: string | null }) {
    if (!name) return <span className="text-muted-foreground">-</span>;
    return href ? <Link href={href} className="font-medium hover:underline">{name}</Link> : <span className="font-medium">{name}</span>;
}

const dash = (v: string | null | undefined) => v || '-';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader><CardTitle>{title}</CardTitle></CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function MyItems({ assets = [], licenses = [], accessories = [], consumables = [], pendingAcceptances = [] }: Props) {
    const empty = !assets.length && !licenses.length && !accessories.length && !consumables.length;

    return (
        <AppLayout title="My items">
            <div className="space-y-6">
                {pendingAcceptances.length > 0 && (
                    <Section title="Pending acceptances">
                        <ul className="divide-y">
                            {pendingAcceptances.map((p) => (
                                <li key={p.id} className="flex items-center justify-between py-2">
                                    <span>{p.item_name}{p.sent_at && <span className="ml-2 text-sm text-muted-foreground">sent {p.sent_at}</span>}</span>
                                    <Button asChild size="sm"><Link href={`/app/my-items/acceptances/${p.id}`}>Review</Link></Button>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {assets.length > 0 && (
                    <Section title="Assets">
                        <Table>
                            <TableHeader><TableRow>
                                <TableHead>Name</TableHead><TableHead>Asset tag</TableHead><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>Since</TableHead><TableHead>Acceptance</TableHead>
                            </TableRow></TableHeader>
                            <TableBody>
                                {assets.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell><Name name={a.name ?? a.asset_tag} href={a.href} /></TableCell>
                                        <TableCell>{dash(a.asset_tag)}</TableCell>
                                        <TableCell>{dash(a.model)}</TableCell>
                                        <TableCell>{dash(a.serial)}</TableCell>
                                        <TableCell>{dash(a.checked_out_at)}</TableCell>
                                        <TableCell>{a.acceptance ? <Badge variant={ACCEPTANCE[a.acceptance].variant}>{ACCEPTANCE[a.acceptance].label}</Badge> : '-'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Section>
                )}

                {licenses.length > 0 && (
                    <Section title="Licenses">
                        <Table>
                            <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Since</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {licenses.map((l, i) => (
                                    <TableRow key={i}><TableCell><Name name={l.name} href={l.href} /></TableCell><TableCell>{dash(l.checked_out_at)}</TableCell></TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Section>
                )}

                {accessories.length > 0 && <QtySection title="Accessories" rows={accessories} />}
                {consumables.length > 0 && <QtySection title="Consumables" rows={consumables} />}

                {empty && <p className="text-muted-foreground">You have no items checked out.</p>}
            </div>
        </AppLayout>
    );
}

function QtySection({ title, rows }: { title: string; rows: QtyRow[] }) {
    return (
        <Section title={title}>
            <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Qty</TableHead><TableHead>Since</TableHead></TableRow></TableHeader>
                <TableBody>
                    {rows.map((r, i) => (
                        <TableRow key={i}><TableCell><Name name={r.name} href={r.href} /></TableCell><TableCell>{r.qty}</TableCell><TableCell>{dash(r.checked_out_at)}</TableCell></TableRow>
                    ))}
                </TableBody>
            </Table>
        </Section>
    );
}
