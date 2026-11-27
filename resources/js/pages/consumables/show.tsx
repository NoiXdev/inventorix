import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, LogOut } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { NumberField } from '@/components/form/number-field';
import { UserPicker } from '@/components/form/user-picker';
import { FormError } from '@/components/form/form-error';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';
import { useCan } from '@/hooks/use-can';

type Option = { value: string; label: string };

interface Consumable {
    id: string; name: string; model_number: string | null; item_no: string | null; qty: number; min_amt: number | null;
    purchase_cost: string | null; purchase_date: string | null; order_number: string | null; requestable: boolean; archived: boolean;
    notes: string | null; image_url: string | null; category_name: string | null; manufacturer_name: string | null;
    location_name: string | null; supplier_name: string | null; handed_out: number; remaining: number; low_stock: boolean;
}

interface Assignment {
    id: string;
    user_name: string | null;
    user_url: string | null;
    qty: number;
    checked_out_at: string | null;
    note: string | null;
}

interface Props {
    consumable: Consumable;
    assignments: Assignment[];
    history: HistoryEntry[];
    /** Users for the check-out dialog; loaded when it opens. */
    checkoutOptions?: { user: Option[] } | null;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

function CheckoutDialog({ consumable, options }: { consumable: Consumable; options?: Props['checkoutOptions'] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ user_id: '', qty: '1', note: '' });
    const disabled = consumable.archived || consumable.remaining === 0;
    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['checkoutOptions'] });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/consumables/${consumable.id}/checkout`, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <>
            <Button variant="outline" onClick={openDialog} disabled={disabled}
                title={consumable.archived ? 'Archived consumables cannot be checked out' : consumable.remaining === 0 ? 'None left' : undefined}>
                <LogOut className="h-4 w-4" /> Check out
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Check out</DialogTitle>
                        <DialogDescription>{options ? `Handed to a user and not taken back. ${consumable.remaining} left.` : 'Loading…'}</DialogDescription>
                    </DialogHeader>
                    {options && (
                        <form onSubmit={submit} className="space-y-4">
                            <UserPicker id="consumable_user_id" label="User" options={options.user} createUrl="/app/users/quick"
                                value={form.data.user_id} onChange={(v) => form.setData('user_id', v)} error={form.errors.user_id} />
                            <NumberField id="consumable_qty" label="Quantity" required step="1" min="1" max={String(consumable.remaining)}
                                value={form.data.qty} onChange={(v) => form.setData('qty', v)} error={form.errors.qty} />
                            <div className="space-y-2">
                                <Label htmlFor="consumable_note">Note</Label>
                                <Textarea id="consumable_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
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

export default function ShowConsumable({ consumable, assignments, history, checkoutOptions }: Props) {
    const can = useCan();

    return (
        <AppLayout title={consumable.name} breadcrumbs={[{ label: 'Consumables', href: '/app/consumables' }, { label: consumable.name }]}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{consumable.name}</h1>
                    <Badge variant={consumable.low_stock ? 'destructive' : 'secondary'}>
                        {consumable.low_stock && <AlertTriangle className="mr-1 h-3 w-3" />}{consumable.remaining} of {consumable.qty} left
                    </Badge>
                    {consumable.archived && <Badge variant="outline">Archived</Badge>}
                </div>
                <div className="flex gap-2">
                    {can('consumables.checkout') && <CheckoutDialog consumable={consumable} options={checkoutOptions} />}
                    {can('consumables.edit') && <Button asChild><Link href={`/app/consumables/${consumable.id}/edit`}>Edit</Link></Button>}
                </div>
            </div>

            <Tabs defaultValue="assignments">
                <TabsList>
                    <TabsTrigger value="assignments">Handed out ({consumable.handed_out})</TabsTrigger>
                    <TabsTrigger value="details">Details</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="assignments" className="mt-4">
                    <Card>
                        <CardContent className="py-4">
                            {assignments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing was handed out yet.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow><TableHead>User</TableHead><TableHead>Quantity</TableHead><TableHead>Date</TableHead><TableHead>Note</TableHead></TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {assignments.map((a) => (
                                            <TableRow key={a.id}>
                                                <TableCell>{a.user_url ? <Link href={a.user_url} className="hover:underline">{a.user_name ?? '—'}</Link> : `${a.user_name ?? '—'} (deleted)`}</TableCell>
                                                <TableCell>{a.qty}</TableCell>
                                                <TableCell>{a.checked_out_at ?? '—'}</TableCell>
                                                <TableCell className="max-w-64 truncate">{a.note ?? ''}</TableCell>
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
                            {consumable.image_url && (
                                <div className="col-span-2 md:col-span-3">
                                    <img src={consumable.image_url} alt="" className="h-32 w-auto rounded-md border object-contain" />
                                </div>
                            )}
                            <Field label="Category">{consumable.category_name}</Field>
                            <Field label="Manufacturer">{consumable.manufacturer_name}</Field>
                            <Field label="Model number">{consumable.model_number}</Field>
                            <Field label="Item number">{consumable.item_no}</Field>
                            <Field label="Location">{consumable.location_name}</Field>
                            <Field label="Quantity">{consumable.qty}</Field>
                            <Field label="Minimum quantity">{consumable.min_amt}</Field>
                            <Field label="Supplier">{consumable.supplier_name}</Field>
                            <Field label="Purchase date">{consumable.purchase_date}</Field>
                            <Field label="Purchase cost">{consumable.purchase_cost}</Field>
                            <Field label="Order number">{consumable.order_number}</Field>
                            <Field label="Requestable">{consumable.requestable ? 'Yes' : 'No'}</Field>
                            {consumable.notes && <div className="col-span-2 md:col-span-3"><Field label="Notes"><span className="whitespace-pre-line">{consumable.notes}</span></Field></div>}
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
