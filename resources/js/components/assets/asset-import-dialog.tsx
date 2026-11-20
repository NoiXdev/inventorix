import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger, DialogDescription } from '@/components/ui/dialog';
import { FormError } from '@/components/form/form-error';

export function AssetImportDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/assets/import', { forceFormData: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild><Button variant="outline">Import</Button></DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import assets</DialogTitle>
                    <DialogDescription>
                        CSV with columns: id, name, asset_tag, status, category, manufacturer, model, serial, location,
                        assigned_user, purchase_date, purchase_cost, supplier, order_number, warranty_months, notes, tags.
                        Use Export as a template; files exported before these columns still import. To create new assets,
                        leave the `id` column empty — rows with an `id` that already exists are reported as errors.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="import_file">File</Label>
                        <input id="import_file" type="file" accept=".csv,.txt"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-secondary file:px-3 file:py-1.5" />
                        <FormError message={form.errors.file} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.file}>Import</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
