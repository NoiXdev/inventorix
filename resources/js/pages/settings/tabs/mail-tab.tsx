import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { NumberField } from '@/components/form/number-field';
import { PasswordField } from '@/components/form/password-field';

interface Opt { value: string; label: string }
interface Props {
    t: any;
    values: Record<string, string | number | null>;
    drivers: Opt[];
    schemes: Opt[];
}

const KeepHint = ({ show }: { show: boolean }) =>
    show ? <p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p> : null;

export function MailTab({ t, values, drivers, schemes }: Props) {
    const form = useForm({
        default_mailer: (values.default_mailer as string) ?? 'smtp',
        from_address: (values.from_address as string) ?? '',
        from_name: (values.from_name as string) ?? '',
        smtp_host: (values.smtp_host as string) ?? '',
        smtp_port: values.smtp_port != null ? String(values.smtp_port) : '',
        smtp_scheme: (values.smtp_scheme as string) ?? '',
        smtp_username: (values.smtp_username as string) ?? '',
        smtp_password: '',
        ses_key: (values.ses_key as string) ?? '',
        ses_secret: '',
        ses_region: (values.ses_region as string) ?? '',
        postmark_token: '',
        postmark_message_stream_id: (values.postmark_message_stream_id as string) ?? '',
        resend_key: '',
        postal_domain: (values.postal_domain as string) ?? '',
        postal_key: '',
    });
    const [testEmail, setTestEmail] = useState('');
    const [open, setOpen] = useState(false);

    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const driver = form.data.default_mailer;
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/mail', { preserveScroll: true }); };
    const sendTest = () => router.post('/app/settings/mail/test', { ...form.data, email: testEmail }, {
        preserveScroll: true,
        onFinish: () => setOpen(false),
    });

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField id="from_address" label={t.mail.field.from_address} value={form.data.from_address} onChange={set('from_address')} error={form.errors.from_address} required />
                <TextField id="from_name" label={t.mail.field.from_name} value={form.data.from_name} onChange={set('from_name')} error={form.errors.from_name} required />
            </div>
            <SelectField id="default_mailer" label={t.mail.field.driver} value={driver} onChange={set('default_mailer')} options={drivers} error={form.errors.default_mailer} />

            {driver === 'smtp' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.smtp}</legend>
                    <TextField id="smtp_host" label={t.mail.field.smtp_host} value={form.data.smtp_host} onChange={set('smtp_host')} error={form.errors.smtp_host} />
                    <NumberField id="smtp_port" label={t.mail.field.smtp_port} step="1" value={form.data.smtp_port} onChange={set('smtp_port')} error={form.errors.smtp_port} />
                    <SelectField id="smtp_scheme" label={t.mail.field.smtp_scheme ?? 'Scheme'} value={form.data.smtp_scheme} onChange={set('smtp_scheme')} options={schemes} nullable />
                    <TextField id="smtp_username" label={t.mail.field.smtp_username} value={form.data.smtp_username} onChange={set('smtp_username')} error={form.errors.smtp_username} />
                    <div className="sm:col-span-2">
                        <PasswordField id="smtp_password" label={t.mail.field.smtp_password} value={form.data.smtp_password} onChange={set('smtp_password')} error={form.errors.smtp_password} />
                        <KeepHint show />
                    </div>
                </fieldset>
            )}
            {driver === 'ses' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.ses}</legend>
                    <TextField id="ses_key" label={t.mail.field.ses_key} value={form.data.ses_key} onChange={set('ses_key')} error={form.errors.ses_key} />
                    <div><PasswordField id="ses_secret" label={t.mail.field.ses_secret} value={form.data.ses_secret} onChange={set('ses_secret')} error={form.errors.ses_secret} /><KeepHint show /></div>
                    <TextField id="ses_region" label={t.mail.field.ses_region} value={form.data.ses_region} onChange={set('ses_region')} error={form.errors.ses_region} />
                </fieldset>
            )}
            {driver === 'postmark' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.postmark}</legend>
                    <div><PasswordField id="postmark_token" label={t.mail.field.postmark_token} value={form.data.postmark_token} onChange={set('postmark_token')} error={form.errors.postmark_token} /><KeepHint show /></div>
                    <TextField id="postmark_message_stream_id" label={t.mail.field.postmark_message_stream_id} value={form.data.postmark_message_stream_id} onChange={set('postmark_message_stream_id')} error={form.errors.postmark_message_stream_id} />
                </fieldset>
            )}
            {driver === 'resend' && (
                <fieldset className="rounded-md border p-4">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.resend}</legend>
                    <div><PasswordField id="resend_key" label={t.mail.field.resend_key} value={form.data.resend_key} onChange={set('resend_key')} error={form.errors.resend_key} /><KeepHint show /></div>
                </fieldset>
            )}
            {driver === 'postal' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.postal}</legend>
                    <TextField id="postal_domain" label={t.mail.field.postal_domain} value={form.data.postal_domain} onChange={set('postal_domain')} error={form.errors.postal_domain} />
                    <div><PasswordField id="postal_key" label={t.mail.field.postal_key} value={form.data.postal_key} onChange={set('postal_key')} error={form.errors.postal_key} /><KeepHint show /></div>
                </fieldset>
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild><Button type="button" variant="outline">{t.mail.test.action}</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader><DialogTitle>{t.mail.test.action}</DialogTitle></DialogHeader>
                        <TextField id="test_email" label={t.mail.test.recipient} value={testEmail} onChange={setTestEmail} />
                        <DialogFooter><Button type="button" onClick={sendTest} disabled={form.processing}>{t.mail.test.action}</Button></DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </form>
    );
}
