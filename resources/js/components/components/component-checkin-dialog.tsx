import type { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { NumberField } from '@/components/form/number-field';
import { FormError } from '@/components/form/form-error';

/** Check some or all of the units in one component_assets row back in. */
export function ComponentCheckinDialog({ rowId, qty, where, onClose }: { rowId: string; qty: number; where: string; onClose: () => void }) {
    const form = useForm({ qty: String(qty), note: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/app/component-assets/${rowId}/checkin`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Check in</DialogTitle>
                    <DialogDescription>{qty} installed in {where}.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <NumberField id="checkin_qty" label="Quantity to check in" required step="1" min="1" max={String(qty)}
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
