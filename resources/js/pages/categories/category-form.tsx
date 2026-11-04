import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { SelectField } from '@/components/form/select-field';
import { SwitchField } from '@/components/form/switch-field';
import { FileField } from '@/components/form/file-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface CategoryInitial {
    id: string;
    name: string;
    category_type: string | null;
    eula_text: string | null;
    use_default_eula: boolean;
    require_acceptance: boolean;
    alert_on_response: boolean;
    checkin_email: boolean;
    notes: string | null;
    image_url: string | null;
}

interface Props {
    initial?: CategoryInitial;
    typeOptions: Option[];
    submitUrl: string;
    method: 'post' | 'put';
}

const BOOLEANS = ['use_default_eula', 'require_acceptance', 'alert_on_response', 'checkin_email', 'remove_image'] as const;

export function CategoryForm({ initial, typeOptions, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        category_type: initial?.category_type ?? 'asset',
        eula_text: initial?.eula_text ?? '',
        use_default_eula: initial?.use_default_eula ?? false,
        require_acceptance: initial?.require_acceptance ?? false,
        alert_on_response: initial?.alert_on_response ?? false,
        checkin_email: initial?.checkin_email ?? false,
        notes: initial?.notes ?? '',
        image: null as File | null,
        remove_image: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Always POST with forceFormData: Inertia cannot send a file over PUT,
        // so an update is method-spoofed. Booleans go out as '1'/'0' so the
        // server's `boolean` rule matches whatever FormData serialisation does.
        form.transform((data) => ({
            ...data,
            ...Object.fromEntries(BOOLEANS.map((key) => [key, data[key] ? '1' : '0'])),
            ...(method === 'put' ? { _method: 'put' } : {}),
        }));

        form.post(submitUrl, { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus
                        value={form.data.name} onChange={(v) => form.setData('name', v)}
                        error={form.errors.name} />
                    <SelectField id="category_type" label="Type" required options={typeOptions}
                        value={form.data.category_type} onChange={(v) => form.setData('category_type', v)}
                        error={form.errors.category_type} />
                    <FileField id="image" label="Image"
                        value={form.data.image}
                        onChange={(file) => form.setData((d) => ({ ...d, image: file, remove_image: false }))}
                        accept="image/png,image/jpeg,image/webp"
                        hint="PNG, JPEG or WebP, up to 2 MB."
                        currentUrl={initial?.image_url ?? null}
                        currentRemoved={form.data.remove_image}
                        onRemoveCurrent={() => form.setData('remove_image', true)}
                        error={form.errors.image} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Acceptance & EULA" description="What users have to agree to when items of this category are handed to them." />
                <FormCardContent className="md:grid-cols-2">
                    <SwitchField id="require_acceptance" label="Require acceptance"
                        description="Users have to accept or decline items in this category."
                        checked={form.data.require_acceptance} onChange={(v) => form.setData('require_acceptance', v)}
                        error={form.errors.require_acceptance} />
                    <SwitchField id="alert_on_response" label="Alert on response"
                        description="Notify admins when a user accepts or declines."
                        checked={form.data.alert_on_response} onChange={(v) => form.setData('alert_on_response', v)}
                        error={form.errors.alert_on_response} />
                    <SwitchField id="checkin_email" label="Check-in/check-out email"
                        description="Email the user when items are checked in or out."
                        checked={form.data.checkin_email} onChange={(v) => form.setData('checkin_email', v)}
                        error={form.errors.checkin_email} />
                    <SwitchField id="use_default_eula" label="Use default EULA"
                        description="Use the default EULA instead of a category-specific one."
                        checked={form.data.use_default_eula} onChange={(v) => form.setData('use_default_eula', v)}
                        error={form.errors.use_default_eula} />
                    {!form.data.use_default_eula && (
                        <div className="md:col-span-2">
                            <TextareaField id="eula_text" label="EULA" rows={8}
                                value={form.data.eula_text} onChange={(v) => form.setData('eula_text', v)}
                                error={form.errors.eula_text} />
                        </div>
                    )}
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Notes" />
                <FormCardContent>
                    <TextareaField id="notes" label="Notes" rows={5}
                        value={form.data.notes} onChange={(v) => form.setData('notes', v)}
                        error={form.errors.notes} />
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
