import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { SwitchField } from '@/components/form/switch-field';
import { TagsInput } from '@/components/form/tags-input';
import { NumberField } from '@/components/form/number-field';

export interface AlertValues {
    enabled: boolean;
    recipients: string[];
    audit_interval_months: number;
    audit_warning_days: number;
    checkin_warning_days: number;
    license_warning_days: number;
    offboarding_warning_days: number;
}

/** Audits and expected check-ins: interval, warning windows, reminder mails. */
export function AlertsTab({ t, values }: { t: any; values: AlertValues }) {
    const form = useForm({
        enabled: Boolean(values.enabled),
        recipients: values.recipients ?? [],
        audit_interval_months: String(values.audit_interval_months ?? 12),
        audit_warning_days: String(values.audit_warning_days ?? 14),
        checkin_warning_days: String(values.checkin_warning_days ?? 7),
        license_warning_days: String(values.license_warning_days ?? 30),
        offboarding_warning_days: String(values.offboarding_warning_days ?? 14),
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/alerts', { preserveScroll: true }); };
    const test = () => router.post('/app/settings/alerts/test', { ...form.data }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <p className="text-sm text-muted-foreground">{t.alerts.field.help}</p>
            <SwitchField id="alerts_enabled" label={t.alerts.field.enabled} checked={form.data.enabled} onChange={(v) => form.setData('enabled', v)} />
            <TagsInput id="alerts_recipients" label={t.alerts.field.recipients} value={form.data.recipients}
                onChange={(v) => form.setData('recipients', v)} error={form.errors.recipients} />
            <div>
                <NumberField id="audit_interval_months" label={t.alerts.field.audit_interval_months} step="1" min="0" max="120"
                    value={form.data.audit_interval_months} onChange={(v) => form.setData('audit_interval_months', v)} error={form.errors.audit_interval_months} />
                <p className="mt-1 text-xs text-muted-foreground">{t.alerts.field.audit_interval_help}</p>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <NumberField id="audit_warning_days" label={t.alerts.field.audit_warning_days} step="1" min="0" max="365"
                    value={form.data.audit_warning_days} onChange={(v) => form.setData('audit_warning_days', v)} error={form.errors.audit_warning_days} />
                <NumberField id="checkin_warning_days" label={t.alerts.field.checkin_warning_days} step="1" min="0" max="365"
                    value={form.data.checkin_warning_days} onChange={(v) => form.setData('checkin_warning_days', v)} error={form.errors.checkin_warning_days} />
                <NumberField id="license_warning_days" label={t.alerts.field.license_warning_days} step="1" min="0" max="365"
                    value={form.data.license_warning_days} onChange={(v) => form.setData('license_warning_days', v)} error={form.errors.license_warning_days} />
                <NumberField id="offboarding_warning_days" label={t.alerts.field.offboarding_warning_days} step="1" min="0" max="365"
                    value={form.data.offboarding_warning_days} onChange={(v) => form.setData('offboarding_warning_days', v)} error={form.errors.offboarding_warning_days} />
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={test}>{t.alerts.test.action}</Button>
            </div>
        </form>
    );
}
