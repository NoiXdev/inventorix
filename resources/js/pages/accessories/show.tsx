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
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { NumberField } from '@/components/form/number-field';
import { UserPicker } from '@/components/form/user-picker';
import { FormError } from '@/components/form/form-error';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';
import { useCan } from '@/hooks/use-can';

type Option = { value: string; label: string };
type TargetType = 'user' | 'location' | 'asset';

interface Accessory {
    id: string; name: string | null; model_number: string | null; qty: number; remaining: number; min_amt: number | null;
    default_purchase_cost: string | null; requestable: boolean; archived: boolean; notes: string | null; image_url: string | null;
    category_name: string | null; manufacturer_name: string | null; location_name: string | null; supplier_name: string | null;
    low_stock: boolean;
}

interface Checkout {
    id: string;
    holder: { type: TargetType | null; name: string | null; url: string | null };
    checked_out_at: string | null;
    note: string | null;
}

interface Props {
    accessory: Accessory;
    checkouts: Checkout[];
    history: HistoryEntry[];
    /** Users, locations and assets for the check-out dialog; loaded when it opens. */
    checkoutOptions?: Record<TargetType, Option[]> | null;
}

const TYPE_LABEL: Record<TargetType, string> = { user: 'User', location: 'Location', asset: 'Asset' };

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

function CheckoutDialog({ accessory, options }: { accessory: Accessory; options?: Props['checkoutOptions'] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ assignee_type: 'user' as TargetType, assignee_id: '', qty: '1', note: '' });
    const disabled = accessory.archived || accessory.remaining === 0;
    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['checkoutOptions'] });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/accessories/${accessory.id}/checkout`, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <>
            <Button variant="outline" onClick={openDialog} disabled={disabled}
                title={accessory.archived ? 'Archived accessories cannot be checked out' : accessory.remaining === 0 ? 'None left' : undefined}>
                <LogOut className="h-4 w-4" /> Check out
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Check out</DialogTitle>
                        <DialogDescription>{options ? `${accessory.remaining} left.` : 'Loading…'}</DialogDescription>
                    </DialogHeader>
                    {options && (
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <SelectField id="accessory_assignee_type" label="Check out to" required
                                    options={(Object.keys(TYPE_LABEL) as TargetType[]).map((t) => ({ value: t, label: TYPE_LABEL[t] }))}
                                    value={form.data.assignee_type}
                                    onChange={(v) => form.setData((d) => ({ ...d, assignee_type: v as TargetType, assignee_id: '' }))} />
                                {form.data.assignee_type === 'user' ? (
                                    <UserPicker id="accessory_assignee_id" label="User" options={options.user} createUrl="/app/users/quick"
                                        value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                                ) : (
                                    <ComboboxField id="accessory_assignee_id" label={TYPE_LABEL[form.data.assignee_type]} required
                                        options={options[form.data.assignee_type]}
                                        value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                                )}
                            </div>
                            <NumberField id="accessory_qty" label="Quantity" required step="1" min="1" max={String(accessory.remaining)}
                                value={form.data.qty} onChange={(v) => form.setData('qty', v)} error={form.errors.qty} />
                            <div className="space-y-2">
                                <Label htmlFor="accessory_note">Note</Label>
                                <Textarea id="accessory_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
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

export default function ShowAccessory({ accessory, checkouts, history, checkoutOptions }: Props) {
    const can = useCan();
    const title = accessory.name ?? 'Accessory';
    const checkin = (c: Checkout) => {
        if (confirm(`Check in the unit of ${c.holder.name ?? 'this holder'}?`)) {
            router.post(`/app/accessory-checkouts/${c.id}/checkin`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={title} breadcrumbs={[{ label: 'Accessories', href: '/app/accessories' }, { label: title }]}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <Badge variant={accessory.low_stock ? 'destructive' : 'secondary'}>
                        {accessory.low_stock && <AlertTriangle className="mr-1 h-3 w-3" />}{accessory.remaining} of {accessory.qty} available
                    </Badge>
                    {accessory.archived && <Badge variant="outline">Archived</Badge>}
                </div>
                <div className="flex gap-2">
                    {can('accessories.checkout') && <CheckoutDialog accessory={accessory} options={checkoutOptions} />}
                    {can('accessories.edit') && <Button asChild><Link href={`/app/accessories/${accessory.id}/edit`}>Edit</Link></Button>}
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
                                        <TableRow><TableHead>Checked out to</TableHead><TableHead>Since</TableHead><TableHead>Note</TableHead><TableHead /></TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {checkouts.map((c) => {
                                            const label = `${c.holder.type ? TYPE_LABEL[c.holder.type] : 'Former holder'}: ${c.holder.name ?? '—'}`;
                                            return (
                                                <TableRow key={c.id}>
                                                    <TableCell>{c.holder.url ? <Link href={c.holder.url} className="hover:underline">{label}</Link> : label}</TableCell>
                                                    <TableCell>{c.checked_out_at ?? '—'}</TableCell>
                                                    <TableCell className="max-w-64 truncate">{c.note ?? ''}</TableCell>
                                                    <TableCell className="text-right">
                                                        {can('accessories.checkin') && (
                                                            <Button size="sm" variant="ghost" onClick={() => checkin(c)}><LogIn className="h-4 w-4" /> Check in</Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
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
                            {accessory.image_url && (
                                <div className="col-span-2 md:col-span-3">
                                    <img src={accessory.image_url} alt="" className="h-32 w-auto rounded-md border object-contain" />
                                </div>
                            )}
                            <Field label="Category">{accessory.category_name}</Field>
                            <Field label="Manufacturer">{accessory.manufacturer_name}</Field>
                            <Field label="Model number">{accessory.model_number}</Field>
                            <Field label="Location">{accessory.location_name}</Field>
                            <Field label="Quantity">{accessory.qty}</Field>
                            <Field label="Minimum quantity">{accessory.min_amt}</Field>
                            <Field label="Default supplier">{accessory.supplier_name}</Field>
                            <Field label="Default purchase cost">{accessory.default_purchase_cost}</Field>
                            <Field label="Requestable">{accessory.requestable ? 'Yes' : 'No'}</Field>
                            {accessory.notes && <div className="col-span-2 md:col-span-3"><Field label="Notes"><span className="whitespace-pre-line">{accessory.notes}</span></Field></div>}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="history" className="mt-4">
                    <AssetHistory history={history} />
                </TabsContent>
            </Tabs>
        </AppLayout>
    );
}
