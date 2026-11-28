import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, LogIn, LogOut } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ComboboxField } from '@/components/form/combobox-field';
import { NumberField } from '@/components/form/number-field';
import { FormError } from '@/components/form/form-error';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';
import { useCan } from '@/hooks/use-can';

type Option = { value: string; label: string };

interface ComponentRecord {
    id: string; name: string; model_number: string | null; serial: string | null; qty: number; min_amt: number | null;
    purchase_cost: string | null; purchase_date: string | null; order_number: string | null;
    notes: string | null; image_url: string | null; category_name: string | null; manufacturer_name: string | null;
    location_name: string | null; supplier_name: string | null; checked_out: number; remaining: number; low_stock: boolean;
}

interface Checkout {
    id: string;
    qty: number;
    asset: { name: string | null; url: string | null };
    checked_out_at: string | null;
    note: string | null;
}

interface Props {
    component: ComponentRecord;
    checkouts: Checkout[];
    history: HistoryEntry[];
    /** Assets for the check-out dialog; loaded when it opens. */
    checkoutOptions?: Option[] | null;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

function CheckoutDialog({ component, options }: { component: ComponentRecord; options?: Option[] | null }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ asset_id: '', qty: '1', note: '' });
    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['checkoutOptions'] });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/components/${component.id}/checkout`, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <>
            <Button variant="outline" onClick={openDialog} disabled={component.remaining === 0} title={component.remaining === 0 ? 'None left' : undefined}>
                <LogOut className="h-4 w-4" /> Check out
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Check out to an asset</DialogTitle>
                        <DialogDescription>{options ? `${component.remaining} left.` : 'Loading…'}</DialogDescription>
                    </DialogHeader>
                    {options && (
                        <form onSubmit={submit} className="space-y-4">
                            <ComboboxField id="component_asset_id" label="Asset" required options={options}
                                value={form.data.asset_id} onChange={(v) => form.setData('asset_id', v)} error={form.errors.asset_id} />
                            <NumberField id="component_qty" label="Quantity" required step="1" min="1" max={String(component.remaining)}
                                value={form.data.qty} onChange={(v) => form.setData('qty', v)} error={form.errors.qty} />
                            <div className="space-y-2">
                                <Label htmlFor="component_note">Note</Label>
                                <Textarea id="component_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                                <FormError message={form.errors.note} />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
                                <Button type="submit" disabled={form.processing}>Check out</Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CheckinDialog({ checkout, onClose }: { checkout: Checkout; onClose: () => void }) {
    const form = useForm({ qty: String(checkout.qty), note: '' });
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/component-assets/${checkout.id}/checkin`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Check in</DialogTitle>
                    <DialogDescription>{checkout.qty} installed in {checkout.asset.name ?? 'this asset'}.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <NumberField id="checkin_qty" label="Quantity to check in" required step="1" min="1" max={String(checkout.qty)}
                        value={form.data.qty} onChange={(v) => form.setData('qty', v)} error={form.errors.qty} />
                    <div className="space-y-2">
                        <Label htmlFor="checkin_note">Note</Label>
                        <Textarea id="checkin_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        <FormError message={form.errors.note} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Check in</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowComponent({ component, checkouts, history, checkoutOptions }: Props) {
    const can = useCan();
    const [checkingIn, setCheckingIn] = useState<Checkout | null>(null);

    return (
        <AppLayout title={component.name} breadcrumbs={[{ label: 'Components', href: '/app/components' }, { label: component.name }]}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{component.name}</h1>
                    <Badge variant={component.low_stock ? 'destructive' : 'secondary'}>
                        {component.low_stock && <AlertTriangle className="mr-1 h-3 w-3" />}{component.remaining} of {component.qty} available
                    </Badge>
                </div>
                <div className="flex gap-2">
                    {can('components.checkout') && <CheckoutDialog component={component} options={checkoutOptions} />}
                    {can('components.edit') && <Button asChild><Link href={`/app/components/${component.id}/edit`}>Edit</Link></Button>}
                </div>
            </div>

            <Tabs defaultValue="checkouts">
                <TabsList>
                    <TabsTrigger value="checkouts">Checked out ({checkouts.length})</TabsTrigger>
                    <TabsTrigger value="details">Details</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="checkouts" className="mt-4">
                    <Card>
                        <CardContent className="py-4">
                            {checkouts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing is checked out.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow><TableHead>Asset</TableHead><TableHead>Quantity</TableHead><TableHead>Since</TableHead><TableHead>Note</TableHead><TableHead /></TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {checkouts.map((c) => (
                                            <TableRow key={c.id}>
                                                <TableCell>{c.asset.url ? <Link href={c.asset.url} className="hover:underline">{c.asset.name}</Link> : (c.asset.name ?? '—')}</TableCell>
                                                <TableCell>{c.qty}</TableCell>
                                                <TableCell>{c.checked_out_at ?? '—'}</TableCell>
                                                <TableCell className="max-w-64 truncate">{c.note ?? ''}</TableCell>
                                                <TableCell className="text-right">
                                                    {can('components.checkin') && (
                                                        <Button size="sm" variant="ghost" onClick={() => setCheckingIn(c)}><LogIn className="h-4 w-4" /> Check in</Button>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="details" className="mt-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-base">Details</CardTitle></CardHeader>
                        <CardContent className="grid grid-cols-2 gap-6 md:grid-cols-3">
                            {component.image_url && <img src={component.image_url} alt="" className="col-span-2 h-24 w-24 rounded border object-contain md:col-span-3" />}
                            <Field label="Category">{component.category_name}</Field>
                            <Field label="Manufacturer">{component.manufacturer_name}</Field>
                            <Field label="Model number">{component.model_number}</Field>
                            <Field label="Serial number">{component.serial}</Field>
                            <Field label="Location">{component.location_name}</Field>
                            <Field label="Available / quantity">{component.remaining} / {component.qty}</Field>
                            <Field label="Minimum quantity">{component.min_amt}</Field>
                            <Field label="Supplier">{component.supplier_name}</Field>
                            <Field label="Purchase date">{component.purchase_date}</Field>
                            <Field label="Purchase cost">{component.purchase_cost}</Field>
                            <Field label="Order number">{component.order_number}</Field>
                            {component.notes && <div className="col-span-2 md:col-span-3"><Field label="Notes"><span className="whitespace-pre-line">{component.notes}</span></Field></div>}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="history" className="mt-4">
                    <AssetHistory history={history} />
                </TabsContent>
            </Tabs>

            {checkingIn && <CheckinDialog checkout={checkingIn} onClose={() => setCheckingIn(null)} />}
        </AppLayout>
    );
}
