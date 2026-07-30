import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props { t: any; values: { app_name: string } }

export function GeneralTab({ t, values }: Props) {
    const form = useForm({ app_name: values.app_name ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/general', { preserveScroll: true }); };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <TextField id="app_name" label={t.general.field.app_name} value={form.data.app_name} onChange={(v) => form.setData('app_name', v)} error={form.errors.app_name} required />
            <Button type="submit" disabled={form.processing}>Save</Button>
        </form>
    );
}
