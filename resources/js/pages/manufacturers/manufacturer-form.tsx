import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props {
    initial?: { id: string; name: string };
    submitUrl: string;
    method: 'post' | 'put';
}

export function ManufacturerForm({ initial, submitUrl, method }: Props) {
    const form = useForm({ name: initial?.name ?? '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(method, submitUrl);
    };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)}
                error={form.errors.name} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
