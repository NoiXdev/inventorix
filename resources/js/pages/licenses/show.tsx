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
import { UserPicker } from '@/components/form/user-picker';
import { FormError } from '@/components/form/form-error';
import { AssetHistory, type HistoryEntry } from '@/components/assets/asset-history';
import { useCan } from '@/hooks/use-can';
import type { LicenseInitial } from './license-form';

type Option = { value: string; label: string };

interface License extends LicenseInitial {
    has_product_key: boolean;
    category_name: string | null; manufacturer_name: string | null; supplier_name: string | null; depreciation_name: string | null;
    free_seats: number; low: boolean;
}

interface Seat {
    id: string;
    retired: boolean;
    holder: { type: 'user' | 'asset'; name: string | null; url: string } | null;
    checked_out_at: string | null;
    notes: string | null;
}

interface Props {
    license: License;
    seats: Seat[];
    history: HistoryEntry[];
    /** Users and assets for the check-out dialog; loaded when it opens. */
    checkoutOptions?: { user: Option[]; asset: Option[] } | null;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

const yesNo = (v: boolean) => (v ? 'Yes' : 'No');

function CheckoutDialog({ licenseId, options, disabled }: { licenseId: string; options?: Props['checkoutOptions']; disabled: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ assignee_type: 'user', assignee_id: '', note: '' });
    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['checkoutOptions'] });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/licenses/${licenseId}/checkout`, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <>
            <Button variant="outline" onClick={openDialog} disabled={disabled} title={disabled ? 'No free seats' : undefined}>
                <LogOut className="h-4 w-4" /> Check out seat
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Check out a seat</DialogTitle>
                        <DialogDescription>{options ? 'A free seat goes to a user or an asset.' : 'Loading…'}</DialogDescription>
                    </DialogHeader>
                    {options && (
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <SelectField id="license_assignee_type" label="Check out to" required
                                    options={[{ value: 'user', label: 'User' }, { value: 'asset', label: 'Asset' }]}
                                    value={form.data.assignee_type} onChange={(v) => form.setData((d) => ({ ...d, assignee_type: v, assignee_id: '' }))} />
                                {form.data.assignee_type === 'user' ? (
                                    <UserPicker id="license_assignee_id" label="User" options={options.user} createUrl="/app/users/quick"
                                        value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                                ) : (
                                    <ComboboxField id="license_assignee_id" label="Asset" required options={options.asset}
                                        value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="license_note">Note</Label>
                                <Textarea id="license_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
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

export default function ShowLicense({ license, seats, history, checkoutOptions }: Props) {
    const can = useCan();
    const [showKey, setShowKey] = useState(false);
    const checkin = (seat: Seat) => {
        const warning = license.reassignable ? '' : ' The license is not reassignable: the seat will be used up.';
        if (confirm(`Check in the seat of ${seat.holder?.name ?? 'this holder'}?${warning}`)) {
            router.post(`/app/license-seats/${seat.id}/checkin`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={license.name} breadcrumbs={[{ label: 'Licenses', href: '/app/licenses' }, { label: license.name }]}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{license.name}</h1>
                    <Badge variant={license.low ? 'destructive' : 'secondary'}>
                        {license.low && <AlertTriangle className="mr-1 h-3 w-3" />}{license.free_seats} of {license.seats} free
                    </Badge>
                </div>
                <div className="flex gap-2">
                    {can('licenses.checkout') && <CheckoutDialog licenseId={license.id} options={checkoutOptions} disabled={license.free_seats === 0} />}
                    {can('licenses.edit') && <Button asChild><Link href={`/app/licenses/${license.id}/edit`}>Edit</Link></Button>}
                </div>
            </div>

            <Tabs defaultValue="seats">
                <TabsList>
                    <TabsTrigger value="seats">Seats ({seats.length})</TabsTrigger>
                    <TabsTrigger value="details">Details</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="seats" className="mt-4">
                    <Card>
                        <CardContent className="py-4">
                            <Table>
                                <TableHeader>
                                    <TableRow><TableHead>Seat</TableHead><TableHead>Checked out to</TableHead><TableHead>Since</TableHead><TableHead>Note</TableHead><TableHead /></TableRow>
                                </TableHeader>
                                <TableBody>
                                    {seats.map((s, i) => (
                                        <TableRow key={s.id}>
                                            <TableCell>{i + 1}</TableCell>
                                            <TableCell>
                                                {s.holder
                                                    ? <Link href={s.holder.url} className="hover:underline">{s.holder.type === 'user' ? 'User' : 'Asset'}: {s.holder.name ?? '—'}</Link>
                                                    : s.retired ? <Badge variant="outline">Used up</Badge> : <span className="text-muted-foreground">Free</span>}
                                            </TableCell>
                                            <TableCell>{s.checked_out_at ?? '—'}</TableCell>
                                            <TableCell className="max-w-64 truncate">{s.notes ?? ''}</TableCell>
                                            <TableCell className="text-right">
                                                {s.holder && can('licenses.checkin') && (
                                                    <Button size="sm" variant="ghost" onClick={() => checkin(s)}><LogIn className="h-4 w-4" /> Check in</Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="details" className="mt-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-base">Details</CardTitle></CardHeader>
                        <CardContent className="grid grid-cols-2 gap-6 md:grid-cols-3">
                            <div className="col-span-2 md:col-span-3">
                                <div className="text-xs text-muted-foreground">Product key</div>
                                {license.product_key !== null ? (
                                    <div className="flex items-start gap-2 text-sm">
                                        <code className="break-all rounded bg-muted px-2 py-1">{showKey ? license.product_key : '••••••••••••'}</code>
                                        <Button size="sm" variant="ghost" onClick={() => setShowKey((v) => !v)}>{showKey ? 'Hide' : 'Show'}</Button>
                                    </div>
                                ) : (
                                    <div className="text-sm text-muted-foreground">{license.has_product_key ? 'Hidden (needs the permission to view product keys)' : '—'}</div>
                                )}
                            </div>
                            <Field label="Category">{license.category_name}</Field>
                            <Field label="Manufacturer">{license.manufacturer_name}</Field>
                            <Field label="Licensed to">{[license.licensed_to_name, license.licensed_to_email].filter(Boolean).join(' · ') || null}</Field>
                            <Field label="Reassignable">{yesNo(license.reassignable)}</Field>
                            <Field label="Maintained">{yesNo(license.maintained)}</Field>
                            <Field label="Minimum free seats">{license.min_amt}</Field>
                            <Field label="Supplier">{license.supplier_name}</Field>
                            <Field label="Purchase date">{license.purchase_date}</Field>
                            <Field label="Purchase cost">{license.purchase_cost}</Field>
                            <Field label="Order number">{license.order_number}</Field>
                            <Field label="Purchase order">{license.purchase_order}</Field>
                            <Field label="Depreciation">{license.depreciation_name}</Field>
                            <Field label="Expiration date">{license.expiration_date}</Field>
                            <Field label="Termination date">{license.termination_date}</Field>
                            <Field label="Requestable">{yesNo(license.requestable)}</Field>
                            {license.notes && <div className="col-span-2 md:col-span-3"><Field label="Notes"><span className="whitespace-pre-line">{license.notes}</span></Field></div>}
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
