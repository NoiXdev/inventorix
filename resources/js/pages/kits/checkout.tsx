import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ComboboxField } from '@/components/form/combobox-field';
import { UserPicker } from '@/components/form/user-picker';
import { FormError } from '@/components/form/form-error';
import { LINE_TYPES, type LineType } from './kit-form';

type Option = { value: string; label: string };

interface Line {
    id: string;
    type: LineType | null;
    name: string | null;
    qty: number;
    available: boolean;
    /** Free assets, seats or units. */
    stock: number;
    /** Model lines only: the assets that can be checked out, and the first `qty` of them. */
    candidates?: Option[];
    preselected?: string[];
}

interface Props {
    kit: { id: string; name: string };
    lines: Line[];
    /** Why the kit cannot be checked out; the server checks again. */
    blocked: string[];
    userOptions: Option[];
    userCreateUrl: string;
}

export default function KitCheckout({ kit, lines, blocked, userOptions, userCreateUrl }: Props) {
    const modelLines = lines.filter((l) => l.type === 'model');
    const otherLines = lines.filter((l) => l.type !== 'model');

    const form = useForm({
        user_id: '',
        // One slot per unit of each model line; '' is an empty slot.
        assets: Object.fromEntries(modelLines.map((l) => [
            l.id, Array.from({ length: l.qty }, (_, i) => l.preselected?.[i] ?? ''),
        ])) as Record<string, string[]>,
        note: '',
    });
    const assets = form.data.assets;
    const chosen = Object.values(assets).flat().filter((v) => v !== '');
    const setAsset = (lineId: string, slot: number, value: string) =>
        form.setData('assets', { ...assets, [lineId]: assets[lineId].map((v, i) => (i === slot ? value : v)) });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            user_id: d.user_id,
            assets: Object.fromEntries(Object.entries(d.assets).map(([id, ids]) => [id, ids.filter((v) => v !== '')])),
            note: d.note,
        }));
        form.post(`/app/kits/${kit.id}/checkout`);
    };

    const errors = form.errors as Record<string, string | undefined>;
    const typeLabel = (type: LineType | null) => LINE_TYPES.find((t) => t.value === type)?.label ?? '—';
    const complete = form.data.user_id !== '' && chosen.length === modelLines.reduce((n, l) => n + l.qty, 0);

    return (
        <AppLayout title={`Check out ${kit.name}`} breadcrumbs={[{ label: 'Kits', href: '/app/kits' }, { label: kit.name, href: `/app/kits/${kit.id}` }, { label: 'Check out' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Check out {kit.name}</h1>

            {blocked.length > 0 && (
                <div role="alert" className="mb-4 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    <p className="font-medium">The kit cannot be checked out:</p>
                    <ul className="mt-1 list-disc pl-5">{blocked.map((b) => <li key={b}>{b}</li>)}</ul>
                </div>
            )}

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <div className="space-y-4">
                    <FormError message={errors.kit} />
                    {modelLines.map((line) => (
                        <Card key={line.id}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">{line.name ?? '—'} × {line.qty}</CardTitle>
                                <CardDescription>{line.available ? `${line.stock} available` : 'Deleted'}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {assets[line.id].map((value, slot) => (
                                    <ComboboxField key={slot} id={`asset_${line.id}_${slot}`} label={`${line.name ?? 'Asset'} ${slot + 1}`} nullable
                                        options={(line.candidates ?? []).filter((o) => o.value === value || !chosen.includes(o.value))}
                                        value={value} onChange={(v) => setAsset(line.id, slot, v)} />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                    <FormError message={errors.assets} />
                    {otherLines.length > 0 && (
                        <Card>
                            <CardContent className="py-4">
                                <Table>
                                    <TableHeader>
                                        <TableRow><TableHead>Type</TableHead><TableHead>Item</TableHead><TableHead>Quantity</TableHead><TableHead>Stock</TableHead></TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {otherLines.map((l) => (
                                            <TableRow key={l.id}>
                                                <TableCell>{typeLabel(l.type)}</TableCell>
                                                <TableCell>{l.name ?? '—'}</TableCell>
                                                <TableCell>{l.qty}</TableCell>
                                                <TableCell className={l.available && l.stock >= l.qty ? '' : 'text-destructive'}>
                                                    {l.available ? `${l.stock} in stock` : 'Deleted'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Check out</CardTitle>
                        <CardDescription>Everything is checked out together. If one item fails, nothing is changed.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <UserPicker id="user_id" label="User" options={userOptions} createUrl={userCreateUrl}
                            value={form.data.user_id} onChange={(v) => form.setData('user_id', v)} error={errors.user_id} />
                        <div className="space-y-2">
                            <Label htmlFor="note">Note</Label>
                            <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            <p className="text-xs text-muted-foreground">Recorded on every item as “Kit: {kit.name}”, followed by this note.</p>
                            <FormError message={errors.note} />
                        </div>
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href={`/app/kits/${kit.id}`}>Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || blocked.length > 0 || !complete}>Check out kit</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
