import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { NumberField } from '@/components/form/number-field';
import { SelectField } from '@/components/form/select-field';
import { SwitchField } from '@/components/form/switch-field';
import { FileField } from '@/components/form/file-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface AssetModelOptions {
    manufacturerOptions: Option[];
    categoryOptions: Option[];
    depreciationOptions: Option[];
    fieldsetOptions: Option[];
}

export interface AssetModelInitial {
    id: string;
    name: string;
    manufacturer_id: string;
    model_number: string | null;
    category_id: string | null;
    depreciation_id: string | null;
    fieldset_id: string | null;
    eol: number | null;
    min_amt: number | null;
    require_serial: boolean;
    requestable: boolean;
    notes: string | null;
    image_url: string | null;
}

interface Props extends AssetModelOptions { initial?: AssetModelInitial; submitUrl: string; method: 'post' | 'put'; }

const BOOLEANS = ['require_serial', 'requestable', 'remove_image'] as const;

export function AssetModelForm({ initial, manufacturerOptions, categoryOptions, depreciationOptions, fieldsetOptions, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        manufacturer_id: initial?.manufacturer_id ?? '',
        model_number: initial?.model_number ?? '',
        category_id: initial?.category_id ?? '',
        depreciation_id: initial?.depreciation_id ?? '',
        fieldset_id: initial?.fieldset_id ?? '',
        eol: initial?.eol != null ? String(initial.eol) : '',
        min_amt: initial?.min_amt != null ? String(initial.min_amt) : '',
        require_serial: initial?.require_serial ?? false,
        requestable: initial?.requestable ?? false,
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
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <SelectField id="manufacturer_id" label="Manufacturer" required options={manufacturerOptions}
                        value={form.data.manufacturer_id} onChange={(v) => form.setData('manufacturer_id', v)} error={form.errors.manufacturer_id} />
                    <TextField id="model_number" label="Model number"
                        value={form.data.model_number} onChange={(v) => form.setData('model_number', v)} error={form.errors.model_number} />
                    <SelectField id="category_id" label="Category" nullable options={categoryOptions}
                        value={form.data.category_id} onChange={(v) => form.setData('category_id', v)} error={form.errors.category_id} />
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
                <FormCardHeader title="Lifecycle & stock" />
                <FormCardContent className="md:grid-cols-2">
                    <SelectField id="depreciation_id" label="Depreciation" nullable options={depreciationOptions}
                        value={form.data.depreciation_id} onChange={(v) => form.setData('depreciation_id', v)} error={form.errors.depreciation_id} />
                    <NumberField id="eol" label="End of life (months)" step="1" min="1"
                        value={form.data.eol} onChange={(v) => form.setData('eol', v)} error={form.errors.eol} />
                    <NumberField id="min_amt" label="Minimum quantity" step="1" min="0"
                        value={form.data.min_amt} onChange={(v) => form.setData('min_amt', v)} error={form.errors.min_amt} />
                    <SelectField id="fieldset_id" label="Fieldset" nullable options={fieldsetOptions}
                        value={form.data.fieldset_id} onChange={(v) => form.setData('fieldset_id', v)} error={form.errors.fieldset_id} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Options" />
                <FormCardContent className="md:grid-cols-2">
                    <SwitchField id="require_serial" label="Require serial number"
                        description="Assets of this model must have a serial number."
                        checked={form.data.require_serial} onChange={(v) => form.setData('require_serial', v)} error={form.errors.require_serial} />
                    <SwitchField id="requestable" label="Requestable"
                        description="Users may request assets of this model."
                        checked={form.data.requestable} onChange={(v) => form.setData('requestable', v)} error={form.errors.requestable} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Notes" />
                <FormCardContent>
                    <TextareaField id="notes" label="Notes" rows={5}
                        value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
