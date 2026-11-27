import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ComboboxField } from '@/components/form/combobox-field';
import { DateField } from '@/components/form/date-field';
import { FormError } from '@/components/form/form-error';

export interface AuditOptions {
    locationOptions: { value: string; label: string }[];
    /** Proposed next audit date (today + audit interval), null without an interval. */
    nextAuditDate: string | null;
}

interface Props {
    assetId: string;
    /** Where the asset is now, shown as what an empty location keeps. */
    locationName: string | null;
    /** Loaded when the dialog opens. */
    options?: AuditOptions | null;
}

/** Audit an asset: confirm it was seen, optionally at another location, and set the next audit. */
export function AuditDialog({ assetId, locationName, options }: Props) {
    const [open, setOpen] = useState(false);
    const openDialog = () => {
        setOpen(true);
        if (!options) router.reload({ only: ['auditOptions'] });
    };

    return (
        <>
            <Button variant="outline" onClick={openDialog}><ClipboardCheck className="h-4 w-4" /> Audit</Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Audit</DialogTitle>
                        <DialogDescription>{options ? 'Confirm the asset was seen. The last audit becomes today.' : 'Loading…'}</DialogDescription>
                    </DialogHeader>
                    {options && <AuditForm assetId={assetId} locationName={locationName} options={options} onDone={() => setOpen(false)} />}
                </DialogContent>
            </Dialog>
        </>
    );
}

function AuditForm({ assetId, locationName, options, onDone }: { assetId: string; locationName: string | null; options: AuditOptions; onDone: () => void }) {
    const form = useForm({ location_id: '', next_audit_date: options.nextAuditDate ?? '', note: '' });
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/app/assets/${assetId}/audit`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <ComboboxField id="audit_location_id" label="Seen at location" nullable options={options.locationOptions}
                    value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                <p className="mt-1 text-xs text-muted-foreground">
                    {locationName ? `Empty: the asset stays at ${locationName}.` : 'Empty: the location is left as it is.'}
                </p>
            </div>
            <DateField id="next_audit_date" label="Next audit" value={form.data.next_audit_date}
                onChange={(v) => form.setData('next_audit_date', v)} error={form.errors.next_audit_date} />
            <div className="space-y-2">
                <Label htmlFor="audit_note">Note</Label>
                <Textarea id="audit_note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                <FormError message={form.errors.note} />
            </div>
            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>Audit</Button>
            </DialogFooter>
        </form>
    );
}
