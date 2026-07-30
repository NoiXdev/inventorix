import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { SwitchField } from '@/components/form/switch-field';
import { TagsInput } from '@/components/form/tags-input';

interface Props { t: any; values: { enabled: boolean; recipients: string[]; lead_days: string[] } }

export function WarrantyTab({ t, values }: Props) {
    const form = useForm({
        enabled: Boolean(values.enabled),
        recipients: values.recipients ?? [],
        lead_days: values.lead_days ?? [],
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/warranty', { preserveScroll: true }); };
    const test = () => router.post('/app/settings/warranty/test', { ...form.data }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <SwitchField id="enabled" label={t.warranty.field.enabled} checked={form.data.enabled} onChange={(v) => form.setData('enabled', v)} />
            <TagsInput id="recipients" label={t.warranty.field.recipients} value={form.data.recipients} onChange={(v) => form.setData('recipients', v)} error={form.errors.recipients} />
            <TagsInput id="lead_days" label={t.warranty.field.lead_days} value={form.data.lead_days} onChange={(v) => form.setData('lead_days', v)} error={form.errors.lead_days} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={test}>{t.warranty.test.action}</Button>
            </div>
        </form>
    );
}
