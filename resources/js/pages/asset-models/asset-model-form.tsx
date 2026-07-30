import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';

interface Props {
    initial?: { id: string; name: string; manufacturer_id: string };
    manufacturerOptions: { value: string; label: string }[];
    submitUrl: string;
    method: 'post' | 'put';
}

export function AssetModelForm({ initial, manufacturerOptions, submitUrl, method }: Props) {
    const form = useForm({ name: initial?.name ?? '', manufacturer_id: initial?.manufacturer_id ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
            <SelectField id="manufacturer_id" label="Manufacturer" required
                value={form.data.manufacturer_id} onChange={(v) => form.setData('manufacturer_id', v)}
                options={manufacturerOptions} error={form.errors.manufacturer_id} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
