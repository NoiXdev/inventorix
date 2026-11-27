import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';

interface Props { t: any; values: { app_name: string; default_eula?: string } }

export function GeneralTab({ t, values }: Props) {
    const form = useForm({ app_name: values.app_name ?? '', default_eula: values.default_eula ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/general', { preserveScroll: true }); };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <TextField id="app_name" label={t.general.field.app_name} value={form.data.app_name} onChange={(v) => form.setData('app_name', v)} error={form.errors.app_name} required />
            <div>
                <TextareaField id="default_eula" label={t.general.field.default_eula} rows={10}
                    value={form.data.default_eula} onChange={(v) => form.setData('default_eula', v)} error={form.errors.default_eula} />
                <p className="mt-1 text-xs text-muted-foreground">{t.general.field.default_eula_help}</p>
            </div>
            <Button type="submit" disabled={form.processing}>Save</Button>
        </form>
    );
}
