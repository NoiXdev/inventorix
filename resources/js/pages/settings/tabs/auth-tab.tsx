import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { PasswordField } from '@/components/form/password-field';

interface Props { t: any; values: Record<string, string | boolean | null> }

export function AuthTab({ t, values }: Props) {
    const form = useForm({
        multi_factor_enabled: Boolean(values.multi_factor_enabled),
        multi_factor_force: Boolean(values.multi_factor_force),
        multi_factor_recoverable: Boolean(values.multi_factor_recoverable),
        microsoft_enabled: Boolean(values.microsoft_enabled),
        microsoft_client_id: (values.microsoft_client_id as string) ?? '',
        microsoft_client_secret: '',
        microsoft_redirect: (values.microsoft_redirect as string) ?? '',
        microsoft_tenant: (values.microsoft_tenant as string) ?? '',
    });
    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/auth', { preserveScroll: true }); };
    const ms = form.data.microsoft_enabled;

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <fieldset className="space-y-3 rounded-md border p-4">
                <legend className="px-1 text-sm font-medium">{t.auth.multi_factor.section}</legend>
                <p className="text-xs text-muted-foreground">MFA-Login wird in einem späteren Schritt umgesetzt; diese Einstellungen werden bereits gespeichert.</p>
                <SwitchField id="mfa_enabled" label={t.auth.multi_factor.field.enabled} checked={form.data.multi_factor_enabled} onChange={(v) => form.setData('multi_factor_enabled', v)} />
                <SwitchField id="mfa_force" label={t.auth.multi_factor.field.force} checked={form.data.multi_factor_force} onChange={(v) => form.setData('multi_factor_force', v)} />
                <SwitchField id="mfa_recoverable" label={t.auth.multi_factor.field.recoverable} checked={form.data.multi_factor_recoverable} onChange={(v) => form.setData('multi_factor_recoverable', v)} />
            </fieldset>
            <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                <legend className="px-1 text-sm font-medium">{t.auth.microsoft.section}</legend>
                <div className="sm:col-span-2"><SwitchField id="ms_enabled" label={t.auth.microsoft.field.enabled} checked={ms} onChange={(v) => form.setData('microsoft_enabled', v)} /></div>
                <TextField id="ms_client_id" label={t.auth.microsoft.field.client_id} value={form.data.microsoft_client_id} onChange={set('microsoft_client_id')} error={form.errors.microsoft_client_id} required={ms} />
                <div><PasswordField id="ms_client_secret" label={t.auth.microsoft.field.client_secret} value={form.data.microsoft_client_secret} onChange={set('microsoft_client_secret')} error={form.errors.microsoft_client_secret} /><p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p></div>
                <TextField id="ms_redirect" label={t.auth.microsoft.field.redirect} value={form.data.microsoft_redirect} onChange={set('microsoft_redirect')} error={form.errors.microsoft_redirect} required={ms} />
                <TextField id="ms_tenant" label={t.auth.microsoft.field.tenant} value={form.data.microsoft_tenant} onChange={set('microsoft_tenant')} error={form.errors.microsoft_tenant} required={ms} />
            </fieldset>
            <Button type="submit" disabled={form.processing}>Save</Button>
        </form>
    );
}
