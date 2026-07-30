import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2, CheckCircle2, RotateCcw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { TextField } from '@/components/form/text-field';
import { DateField } from '@/components/form/date-field';
import { FormError } from '@/components/form/form-error';

export interface IncidentItem {
    id: number; title: string; notes: string | null;
    open_date: string | null; closed_date: string | null;
    status: 'open' | 'closed'; created_at: string | null;
}
interface Props { assetId: string; incidents: IncidentItem[]; }

const today = () => new Date().toISOString().slice(0, 10);

export function AssetIncidents({ assetId, incidents }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<IncidentItem | null>(null);
    const form = useForm({ title: '', open_date: today(), closed_date: '', notes: '' });

    const openCreate = () => {
        setEditing(null);
        form.setData({ title: '', open_date: today(), closed_date: '', notes: '' });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (i: IncidentItem) => {
        setEditing(i);
        form.setData({ title: i.title, open_date: i.open_date ?? '', closed_date: i.closed_date ?? '', notes: i.notes ?? '' });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { setDialogOpen(false); form.reset(); } };
        if (editing) form.put(`/app/assets/${assetId}/incidents/${editing.id}`, opts);
        else form.post(`/app/assets/${assetId}/incidents`, opts);
    };

    const act = (id: number, action: 'close' | 'reopen') =>
        router.post(`/app/assets/${assetId}/incidents/${id}/${action}`, {}, { preserveScroll: true });
    const remove = (id: number) => {
        if (confirm('Delete this incident?')) router.delete(`/app/assets/${assetId}/incidents/${id}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button onClick={openCreate}>New incident</Button>
            </div>

            {incidents.length === 0 ? (
                <p className="text-sm text-muted-foreground">No incidents yet.</p>
            ) : (
                <div className="rounded-md border divide-y">
                    {incidents.map((i) => (
                        <div key={i.id} className="flex items-start gap-3 px-3 py-2 text-sm">
                            <Badge variant={i.status === 'open' ? 'default' : 'outline'}>{i.status === 'open' ? 'Open' : 'Closed'}</Badge>
                            <div className="min-w-0 flex-1">
                                <div className="font-medium">{i.title}</div>
                                <div className="text-xs text-muted-foreground">
                                    Reported {i.open_date ?? '—'}{i.closed_date ? ` · Resolved ${i.closed_date}` : ''}
                                </div>
                                {i.notes && <div className="mt-1 line-clamp-2 text-xs text-muted-foreground">{i.notes}</div>}
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Button variant="ghost" size="icon" aria-label={`Edit ${i.title}`} onClick={() => openEdit(i)}><Pencil className="h-4 w-4" /></Button>
                                {i.status === 'open' ? (
                                    <Button variant="ghost" size="icon" aria-label={`Mark ${i.title} closed`} onClick={() => act(i.id, 'close')}><CheckCircle2 className="h-4 w-4" /></Button>
                                ) : (
                                    <Button variant="ghost" size="icon" aria-label={`Reopen ${i.title}`} onClick={() => act(i.id, 'reopen')}><RotateCcw className="h-4 w-4" /></Button>
                                )}
                                <Button variant="ghost" size="icon" aria-label={`Delete ${i.title}`} onClick={() => remove(i.id)}><Trash2 className="h-4 w-4" /></Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit incident' : 'New incident'}</DialogTitle>
                        <DialogDescription>Track an incident for this asset.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <TextField id="title" label="Title" required value={form.data.title}
                            onChange={(v) => form.setData('title', v)} error={form.errors.title} />
                        <div className="grid grid-cols-2 gap-4">
                            <DateField id="open_date" label="Open date" required value={form.data.open_date}
                                onChange={(v) => form.setData('open_date', v)} error={form.errors.open_date} />
                            <DateField id="closed_date" label="Closed date" value={form.data.closed_date}
                                onChange={(v) => form.setData('closed_date', v)} error={form.errors.closed_date} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            <FormError message={form.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setDialogOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}>Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
