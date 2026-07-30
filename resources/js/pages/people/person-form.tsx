import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props {
    initial?: { id: string; firstname: string; lastname: string; email: string | null };
    submitUrl: string;
    method: 'post' | 'put';
}

export function PersonForm({ initial, submitUrl, method }: Props) {
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        email: initial?.email ?? '',
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField id="firstname" label="First name" required autoFocus value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                <TextField id="lastname" label="Last name" required value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
            </div>
            <TextField id="email" label="Email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
