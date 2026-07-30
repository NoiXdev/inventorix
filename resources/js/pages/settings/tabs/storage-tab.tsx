import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { PasswordField } from '@/components/form/password-field';

interface Props { t: any; values: Record<string, string | boolean | null> }

export function StorageTab({ t, values }: Props) {
    const form = useForm({
        key: (values.key as string) ?? '',
        secret: '',
        region: (values.region as string) ?? '',
        bucket: (values.bucket as string) ?? '',
        endpoint: (values.endpoint as string) ?? '',
        use_path_style_endpoint: Boolean(values.use_path_style_endpoint),
        url: (values.url as string) ?? '',
    });
    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/storage', { preserveScroll: true }); };
    const test = () => router.post('/app/settings/storage/test', { ...form.data }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="grid max-w-2xl gap-4 sm:grid-cols-2">
            <TextField id="key" label={t.storage.field.key} value={form.data.key} onChange={set('key')} error={form.errors.key} />
            <div><PasswordField id="secret" label={t.storage.field.secret} value={form.data.secret} onChange={set('secret')} error={form.errors.secret} /><p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p></div>
            <TextField id="region" label={t.storage.field.region} value={form.data.region} onChange={set('region')} error={form.errors.region} />
            <TextField id="bucket" label={t.storage.field.bucket} value={form.data.bucket} onChange={set('bucket')} error={form.errors.bucket} />
            <TextField id="endpoint" label={t.storage.field.endpoint} value={form.data.endpoint} onChange={set('endpoint')} error={form.errors.endpoint} />
            <TextField id="url" label={t.storage.field.url} value={form.data.url} onChange={set('url')} error={form.errors.url} />
            <div className="sm:col-span-2"><SwitchField id="use_path_style_endpoint" label={t.storage.field.use_path_style_endpoint} checked={form.data.use_path_style_endpoint} onChange={(v) => form.setData('use_path_style_endpoint', v)} /></div>
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={test}>{t.storage.test.action}</Button>
            </div>
        </form>
    );
}
