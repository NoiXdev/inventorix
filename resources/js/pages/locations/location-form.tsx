import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { FileField } from '@/components/form/file-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface LocationOptions {
    parentOptions: Option[];
    managerOptions: Option[];
    countryOptions: Option[];
}

export interface LocationInitial {
    id: string;
    name: string | null;
    parent_id: string | null;
    manager_id: string | null;
    address: string | null;
    address2: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    country: string | null;
    phone: string | null;
    fax: string | null;
    currency: string | null;
    ldap_ou: string | null;
    notes: string | null;
    image_url: string | null;
}

interface Props {
    initial?: LocationInitial;
    options: LocationOptions;
    submitUrl: string;
    method: 'post' | 'put';
}

const TEXT_KEYS = [
    'name', 'parent_id', 'manager_id', 'address', 'address2', 'city', 'state', 'zip', 'country',
    'phone', 'fax', 'currency', 'ldap_ou', 'notes',
] as const;
type TextKey = (typeof TEXT_KEYS)[number];

export function LocationForm({ initial, options, submitUrl, method }: Props) {
    const form = useForm({
        ...(Object.fromEntries(TEXT_KEYS.map((key) => [key, initial?.[key] ?? ''])) as Record<TextKey, string>),
        image: null as File | null,
        remove_image: false,
    });

    const text = (key: TextKey, label: string, extra: { required?: boolean; autoFocus?: boolean; autoComplete?: string; placeholder?: string } = {}) => (
        <TextField id={key} label={label} {...extra}
            value={form.data[key]} onChange={(v) => form.setData(key, v)}
            error={form.errors[key]} />
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Always POST with forceFormData: Inertia cannot send a file over PUT,
        // so an update is method-spoofed. The boolean goes out as '1'/'0' so the
        // server's `boolean` rule matches whatever FormData serialisation does.
        form.transform((data) => ({
            ...data,
            remove_image: data.remove_image ? '1' : '0',
            ...(method === 'put' ? { _method: 'put' } : {}),
        }));

        form.post(submitUrl, { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    {text('name', 'Name', { required: true, autoFocus: true })}
                    <ComboboxField id="parent_id" label="Parent location" nullable options={options.parentOptions}
                        value={form.data.parent_id} onChange={(v) => form.setData('parent_id', v)}
                        error={form.errors.parent_id} />
                    <ComboboxField id="manager_id" label="Manager" nullable options={options.managerOptions}
                        value={form.data.manager_id} onChange={(v) => form.setData('manager_id', v)}
                        error={form.errors.manager_id} />
                    {text('currency', 'Currency', { placeholder: 'EUR' })}
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
                <FormCardHeader title="Address" />
                <FormCardContent className="md:grid-cols-2">
                    {text('address', 'Address', { autoComplete: 'address-line1' })}
                    {text('address2', 'Address line 2', { autoComplete: 'address-line2' })}
                    {text('zip', 'ZIP', { autoComplete: 'postal-code' })}
                    {text('city', 'City', { autoComplete: 'address-level2' })}
                    {text('state', 'State', { autoComplete: 'address-level1' })}
                    <ComboboxField id="country" label="Country" nullable options={options.countryOptions}
                        value={form.data.country} onChange={(v) => form.setData('country', v)}
                        error={form.errors.country} />
                    {text('phone', 'Phone', { autoComplete: 'tel' })}
                    {text('fax', 'Fax')}
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Directory" description="Users in this LDAP organisational unit belong to this location." />
                <FormCardContent className="md:grid-cols-2">
                    {text('ldap_ou', 'LDAP OU', { placeholder: 'OU=Berlin,DC=example,DC=com' })}
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
