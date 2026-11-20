import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { UserPicker } from '@/components/form/user-picker';
import { DateField } from '@/components/form/date-field';
import { FormError } from '@/components/form/form-error';

type Option = { value: string; label: string };
type AssigneeType = 'user' | 'location' | 'asset';

export interface CheckoutOptions {
    assigneeOptions: Record<AssigneeType, Option[]>;
    userCreateUrl: string;
    deployableStatusOptions: Option[];
    statusOptions: Option[];
    locationOptions: Option[];
    checkoutStatusId: string;
    checkinStatusId: string;
    rtdLocationName: string | null;
}

const TARGET_TYPES: { value: AssigneeType; label: string }[] = [
    { value: 'user', label: 'User' },
    { value: 'location', label: 'Location' },
    { value: 'asset', label: 'Asset' },
];

const today = () => new Date().toISOString().slice(0, 10);

interface Props {
    assetId: string;
    checkedOut: boolean;
    /** Why the asset cannot be checked out; null when it can. */
    blocker: string | null;
    options?: CheckoutOptions;
}

/**
 * The check-out or check-in button of an asset, with its dialog. The
 * options (users, locations, statuses) are only loaded when the dialog opens.
 */
export function CheckoutDialogs({ assetId, checkedOut, blocker, options }: Props) {
    const [open, setOpen] = useState(false);

    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['checkoutOptions'] });
    };

    const button = checkedOut ? (
        <Button variant="outline" onClick={openDialog}><LogIn className="h-4 w-4" /> Check in</Button>
    ) : (
        <Button variant="outline" onClick={openDialog} disabled={blocker !== null}><LogOut className="h-4 w-4" /> Check out</Button>
    );

    return (
        <>
            {blocker && !checkedOut ? (
                <TooltipProvider>
                    <Tooltip>
                        {/* A disabled button gets no pointer events; the span carries the tooltip. */}
                        <TooltipTrigger asChild><span tabIndex={0}>{button}</span></TooltipTrigger>
                        <TooltipContent>{blocker}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            ) : button}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    {!options ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>{checkedOut ? 'Check in' : 'Check out'}</DialogTitle>
                                <DialogDescription>Loading…</DialogDescription>
                            </DialogHeader>
                        </>
                    ) : checkedOut ? (
                        <CheckinForm assetId={assetId} options={options} onDone={() => setOpen(false)} />
                    ) : (
                        <CheckoutForm assetId={assetId} options={options} onDone={() => setOpen(false)} />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function NoteField({ value, onChange, error }: { value: string; onChange: (v: string) => void; error?: string }) {
    return (
        <div className="space-y-2">
            <Label htmlFor="note">Note</Label>
            <Textarea id="note" value={value} onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}

function CheckoutForm({ assetId, options, onDone }: { assetId: string; options: CheckoutOptions; onDone: () => void }) {
    const deployable = options.deployableStatusOptions;
    const form = useForm({
        assignee_type: 'user' as AssigneeType,
        assignee_id: '',
        status_id: deployable.some((s) => s.value === options.checkoutStatusId) ? options.checkoutStatusId : '',
        checkout_at: today(),
        expected_checkin: '',
        note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/assets/${assetId}/checkout`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Check out</DialogTitle>
                <DialogDescription>Assign this asset to a user, a location or another asset.</DialogDescription>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <SelectField id="assignee_type" label="Check out to" required options={TARGET_TYPES}
                        value={form.data.assignee_type}
                        onChange={(v) => form.setData((d) => ({ ...d, assignee_type: v as AssigneeType, assignee_id: '' }))}
                        error={form.errors.assignee_type} />
                    {form.data.assignee_type === 'user' ? (
                        <UserPicker id="assignee_id" label="User" options={options.assigneeOptions.user} createUrl={options.userCreateUrl}
                            value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                    ) : (
                        <ComboboxField id="assignee_id" label={form.data.assignee_type === 'location' ? 'Location' : 'Asset'} required
                            options={options.assigneeOptions[form.data.assignee_type]}
                            value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                    )}
                </div>
                <SelectField id="status_id" label="Status (— keeps the current one)" nullable options={deployable}
                    value={form.data.status_id} onChange={(v) => form.setData('status_id', v)} error={form.errors.status_id} />
                <div className="grid grid-cols-2 gap-4">
                    <DateField id="checkout_at" label="Checkout date" value={form.data.checkout_at}
                        onChange={(v) => form.setData('checkout_at', v)} error={form.errors.checkout_at} />
                    <DateField id="expected_checkin" label="Expected check-in" value={form.data.expected_checkin}
                        onChange={(v) => form.setData('expected_checkin', v)} error={form.errors.expected_checkin} />
                </div>
                <NoteField value={form.data.note} onChange={(v) => form.setData('note', v)} error={form.errors.note} />
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>Check out</Button>
                </DialogFooter>
            </form>
        </>
    );
}

function CheckinForm({ assetId, options, onDone }: { assetId: string; options: CheckoutOptions; onDone: () => void }) {
    const form = useForm({
        status_id: options.statusOptions.some((s) => s.value === options.checkinStatusId) ? options.checkinStatusId : '',
        location_id: '',
        checkin_at: today(),
        note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/assets/${assetId}/checkin`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Check in</DialogTitle>
                <DialogDescription>Take this asset back. It is no longer assigned afterwards.</DialogDescription>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <SelectField id="status_id" label="Status (— keeps the current one)" nullable options={options.statusOptions}
                    value={form.data.status_id} onChange={(v) => form.setData('status_id', v)} error={form.errors.status_id} />
                <div>
                    <ComboboxField id="location_id" label="Location" nullable options={options.locationOptions}
                        value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                    <p className="mt-1 text-xs text-muted-foreground">
                        {options.rtdLocationName
                            ? `Empty: back to the default location (${options.rtdLocationName}).`
                            : 'Empty: the asset stays where it is (it has no default location).'}
                    </p>
                </div>
                <DateField id="checkin_at" label="Check-in date" value={form.data.checkin_at}
                    onChange={(v) => form.setData('checkin_at', v)} error={form.errors.checkin_at} />
                <NoteField value={form.data.note} onChange={(v) => form.setData('note', v)} error={form.errors.note} />
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>Check in</Button>
                </DialogFooter>
            </form>
        </>
    );
}
