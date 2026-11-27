import { useMemo, useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { ScanLine, X } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FormError } from '@/components/form/form-error';
import { CheckoutFields, checkoutDefaults, type CheckoutData, type CheckoutOptions } from '@/components/assets/checkout-dialogs';

export interface BulkAsset {
    id: string;
    name: string;
    asset_tag: string | null;
    serial: string | null;
    model_name: string | null;
    status_name: string | null;
}

interface Props {
    /** Assets that can be checked out: unassigned, with a deployable status. */
    availableAssets: BulkAsset[];
    /** Selected in the asset list and available. */
    preselectedIds: string[];
    /** Selected in the asset list but not available, with why. */
    skipped: { name: string; reason: string }[];
    checkoutOptions: CheckoutOptions;
    maxAssets: number;
}

const norm = (v: string | null) => (v ?? '').trim().toLowerCase();
const searchText = (a: BulkAsset) => [a.name, a.asset_tag, a.serial, a.model_name].map(norm).join(' ');

/** The error about the selected assets themselves (asset_ids or asset_ids.N). */
const assetIdsError = (errors: Record<string, string>) =>
    Object.entries(errors).find(([k]) => k === 'asset_ids' || k.startsWith('asset_ids.'))?.[1];

export default function BulkCheckout({ availableAssets, preselectedIds, skipped, checkoutOptions, maxAssets }: Props) {
    const form = useForm<CheckoutData & { asset_ids: string[] }>({
        ...checkoutDefaults(checkoutOptions),
        asset_ids: preselectedIds,
    });
    const [query, setQuery] = useState('');
    const [notice, setNotice] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const byId = useMemo(() => new Map(availableAssets.map((a) => [a.id, a])), [availableAssets]);
    const selected = form.data.asset_ids.map((id) => byId.get(id)).filter((a): a is BulkAsset => a !== undefined);

    const q = norm(query);
    const suggestions = q === ''
        ? []
        : availableAssets.filter((a) => !form.data.asset_ids.includes(a.id) && searchText(a).includes(q)).slice(0, 8);

    const add = (asset: BulkAsset) => {
        if (!form.data.asset_ids.includes(asset.id)) {
            form.setData('asset_ids', [...form.data.asset_ids, asset.id]);
        }
        setQuery('');
        setNotice(null);
        inputRef.current?.focus();
    };
    const remove = (id: string) => form.setData('asset_ids', form.data.asset_ids.filter((x) => x !== id));

    // Enter adds the asset whose tag or serial matches exactly (a scanner
    // types the code and Enter), else the only suggestion.
    const onEnter = () => {
        if (q === '') return;
        const exact = availableAssets.find((a) => norm(a.asset_tag) === q || norm(a.serial) === q);
        if (exact) {
            if (form.data.asset_ids.includes(exact.id)) {
                setNotice(`${exact.name} is already in the list.`);
                setQuery('');
            } else {
                add(exact);
            }
            return;
        }
        if (suggestions.length === 1) {
            add(suggestions[0]);
            return;
        }
        setNotice(suggestions.length === 0
            ? `No available asset matches “${query.trim()}”. It may be checked out already or have a status that is not deployable.`
            : 'Several assets match — pick one from the list.');
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/assets/bulk-checkout');
    };

    const tooMany = selected.length > maxAssets;

    return (
        <AppLayout title="Bulk checkout" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'Bulk checkout' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Bulk checkout</h1>
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Assets</CardTitle>
                        <CardDescription>
                            Search by name, asset tag, serial or model, or scan a tag or serial. Only assets that are not checked out and have a deployable status are offered.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {skipped.length > 0 && (
                            <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <p className="font-medium">
                                    {skipped.length === 1 ? '1 selected asset was' : `${skipped.length} selected assets were`} left out:
                                </p>
                                <ul className="mt-1 max-h-32 list-disc space-y-0.5 overflow-y-auto pl-5">
                                    {skipped.map((s, i) => <li key={i}>{s.name} — {s.reason}</li>)}
                                </ul>
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="asset_search">Add asset</Label>
                            <div className="relative">
                                <ScanLine className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="asset_search" ref={inputRef} autoFocus autoComplete="off" className="pl-9"
                                    placeholder="Name, asset tag, serial…"
                                    value={query}
                                    onChange={(e) => { setQuery(e.target.value); setNotice(null); }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') { e.preventDefault(); onEnter(); }
                                        if (e.key === 'Escape') setQuery('');
                                    }}
                                />
                                {suggestions.length > 0 && (
                                    <ul role="listbox" className="absolute top-full z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover text-sm shadow-md">
                                        {suggestions.map((a) => (
                                            <li key={a.id} role="option" aria-selected={false}>
                                                <button type="button" onClick={() => add(a)}
                                                    className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-accent">
                                                    <span className="font-medium">{a.name}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {[a.asset_tag, a.serial, a.model_name].filter(Boolean).join(' · ')}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            {notice && <p className="text-sm text-muted-foreground">{notice}</p>}
                        </div>

                        {selected.length === 0 ? (
                            <p className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">No assets selected yet.</p>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Asset tag</TableHead>
                                            <TableHead>Serial</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-10" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {selected.map((a) => (
                                            <TableRow key={a.id}>
                                                <TableCell>
                                                    <Link href={`/app/assets/${a.id}`} className="font-medium hover:underline">{a.name}</Link>
                                                    {a.model_name && <div className="text-xs text-muted-foreground">{a.model_name}</div>}
                                                </TableCell>
                                                <TableCell>{a.asset_tag ?? '—'}</TableCell>
                                                <TableCell>{a.serial ?? '—'}</TableCell>
                                                <TableCell>{a.status_name ?? '—'}</TableCell>
                                                <TableCell>
                                                    <Button type="button" variant="ghost" size="icon" onClick={() => remove(a.id)} aria-label={`Remove ${a.name}`}>
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>{selected.length === 1 ? '1 asset' : `${selected.length} assets`} selected{tooMany ? ` — at most ${maxAssets} at once` : ''}</span>
                            {selected.length > 0 && (
                                <Button type="button" variant="link" size="sm" className="h-auto p-0" onClick={() => form.setData('asset_ids', [])}>Clear</Button>
                            )}
                        </div>
                        <FormError message={assetIdsError(form.errors)} />
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Check out to</CardTitle>
                        <CardDescription>All selected assets go to the same user, location or asset. If one of them cannot be checked out, none are.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <CheckoutFields data={form.data} setData={form.setData} errors={form.errors} options={checkoutOptions} />
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href="/app/assets">Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || selected.length === 0 || tooMany}>
                                Check out {selected.length || ''}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
