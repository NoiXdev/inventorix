import { useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, ScanLine, XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ComboboxField } from '@/components/form/combobox-field';
import { DateField } from '@/components/form/date-field';

type Option = { value: string; label: string };

interface Props {
    locationOptions: Option[];
    /** Proposed next audit date (today + audit interval). */
    nextAuditDate: string | null;
}

export interface AuditScanResult {
    key: number;
    code: string;
    ok: boolean;
    message: string;
    asset?: { id: string; name: string; asset_tag: string | null; location: string | null; next_audit_date: string | null };
}

/**
 * Inventory by scanning (Snipe-IT's quick scan audit): every scanned asset tag
 * or serial is audited at once with the location, next audit and note set here.
 */
export default function AuditScan({ locationOptions, nextAuditDate }: Props) {
    const [locationId, setLocationId] = useState('');
    const [nextAudit, setNextAudit] = useState(nextAuditDate ?? '');
    const [note, setNote] = useState('');
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [results, setResults] = useState<AuditScanResult[]>([]);
    const counter = useRef(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const scan = async () => {
        const value = code.trim();
        if (value === '' || busy) return;
        setBusy(true);
        const key = ++counter.current;
        try {
            const { data } = await axios.post('/app/assets/audit/scan', {
                code: value, location_id: locationId, next_audit_date: nextAudit, note,
            });
            setResults((r) => [{ key, code: value, ok: true, message: 'Audited', asset: data }, ...r]);
        } catch (e) {
            const message = axios.isAxiosError(e)
                ? (e.response?.data?.message as string | undefined) ?? 'The audit failed.'
                : 'The audit failed.';
            setResults((r) => [{ key, code: value, ok: false, message }, ...r]);
        } finally {
            setCode('');
            setBusy(false);
            inputRef.current?.focus();
        }
    };

    const audited = results.filter((r) => r.ok).length;

    return (
        <AppLayout title="Audit" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'Audit' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Audit</h1>
            <div className="grid gap-6 lg:grid-cols-[2fr_3fr]">
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>For every scanned asset</CardTitle>
                        <CardDescription>The last audit becomes today. An empty location leaves each asset where it is.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ComboboxField id="scan_location_id" label="Seen at location" nullable options={locationOptions}
                            value={locationId} onChange={setLocationId} />
                        <DateField id="scan_next_audit_date" label="Next audit" value={nextAudit} onChange={setNextAudit} />
                        <div className="space-y-2">
                            <Label htmlFor="scan_note">Note</Label>
                            <Textarea id="scan_note" value={note} onChange={(e) => setNote(e.target.value)} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Scan</CardTitle>
                        <CardDescription>Scan or type an asset tag or serial and press Enter.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="audit_code">Asset tag or serial</Label>
                            <div className="relative">
                                <ScanLine className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input id="audit_code" ref={inputRef} autoFocus autoComplete="off" className="pl-9" value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); void scan(); } }} />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">{audited === 1 ? '1 asset audited' : `${audited} assets audited`} in this session.</p>
                        {results.length > 0 && (
                            <ul className="divide-y rounded-md border text-sm" aria-label="Scan results">
                                {results.map((r) => (
                                    <li key={r.key} className="flex items-start gap-3 px-3 py-2">
                                        {r.ok
                                            ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-600" aria-label="Audited" />
                                            : <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" aria-label="Failed" />}
                                        <div className="min-w-0 flex-1">
                                            {r.asset
                                                ? <Link href={`/app/assets/${r.asset.id}`} className="font-medium hover:underline">{r.asset.name}</Link>
                                                : <span className="font-medium">{r.code}</span>}
                                            <div className="text-xs text-muted-foreground">
                                                {r.asset
                                                    ? [r.asset.location, r.asset.next_audit_date && `next audit ${r.asset.next_audit_date}`].filter(Boolean).join(' · ') || r.message
                                                    : r.message}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
