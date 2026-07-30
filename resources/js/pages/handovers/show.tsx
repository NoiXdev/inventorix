import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface HandoverDetail {
    id: string; type_label: string | null; recipient_name: string; recipient_email: string | null;
    recipient_kind_label: string | null; accessories: string | null; condition_notes: string | null;
    terms_text: string; created_by_name: string | null; signed_at: string | null;
    pdf_ready: boolean; pdf_url: string | null;
}
interface AssetRow { id: string; label: string; state_from: string | null; state_to: string | null; }

export default function HandoverShow({ handover, assets }: { handover: HandoverDetail; assets: AssetRow[] }) {
    return (
        <AppLayout title="Handover" breadcrumbs={[{ label: 'Handovers', href: '/app/handovers' }, { label: handover.recipient_name }]}>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">Handover</h1>
                    <Badge variant="secondary">{handover.type_label}</Badge>
                </div>
                {handover.pdf_ready && handover.pdf_url
                    ? <Button asChild><a href={handover.pdf_url}>Download PDF</a></Button>
                    : <span className="text-sm text-muted-foreground">PDF is generating…</span>}
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Recipient</CardTitle></CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <div>{handover.recipient_name} <span className="text-muted-foreground">({handover.recipient_kind_label})</span></div>
                        <div className="text-muted-foreground">{handover.recipient_email ?? '—'}</div>
                        <div className="text-xs text-muted-foreground">Signed {handover.signed_at ?? '—'} · by {handover.created_by_name ?? '—'}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Details</CardTitle></CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <div><span className="text-muted-foreground">Accessories:</span> {handover.accessories ?? '—'}</div>
                        <div><span className="text-muted-foreground">Condition:</span> {handover.condition_notes ?? '—'}</div>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Assets</CardTitle></CardHeader>
                <CardContent>
                    <ul className="divide-y text-sm">
                        {assets.map((a) => (
                            <li key={a.id} className="flex items-center justify-between py-2">
                                <span>{a.label}</span>
                                <span className="text-xs text-muted-foreground">{a.state_from} → {a.state_to}</span>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
