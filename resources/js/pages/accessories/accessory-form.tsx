import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { NumberField } from '@/components/form/number-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { SwitchField } from '@/components/form/switch-field';
import { FileField } from '@/components/form/file-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface AccessoryOptions {
    categoryOptions: Option[];
    manufacturerOptions: Option[];
    supplierOptions: Option[];
    locationOptions: Option[];
}

export interface AccessoryInitial {
    id: string;
    name: string | null;
    category_id: string | null;
    manufacturer_id: string | null;
    model_number: string | null;
    location_id: string | null;
    default_supplier_id: string | null;
    qty: number;
    min_amt: number | null;
    default_purchase_cost: string | null;
    requestable: boolean;
    archived: boolean;
    notes: string | null;
    image_url: string | null;
}

interface Props {
    initial?: AccessoryInitial;
    options: AccessoryOptions;
    submitUrl: string;
    method: 'post' | 'put';
}

const BOOLEANS = ['requestable', 'archived', 'remove_image'] as const;

export function AccessoryForm({ initial, options, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        category_id: initial?.category_id ?? '',
        manufacturer_id: initial?.manufacturer_id ?? '',
        model_number: initial?.model_number ?? '',
        location_id: initial?.location_id ?? '',
        default_supplier_id: initial?.default_supplier_id ?? '',
        qty: String(initial?.qty ?? 0),
        min_amt: initial?.min_amt == null ? '' : String(initial.min_amt),
        default_purchase_cost: initial?.default_purchase_cost ?? '',
        requestable: initial?.requestable ?? false,
        archived: initial?.archived ?? false,
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
                    <ComboboxField id="category_id" label="Category" nullable options={options.categoryOptions}
                        value={form.data.category_id} onChange={(v) => form.setData('category_id', v)} error={form.errors.category_id} />
                    <ComboboxField id="manufacturer_id" label="Manufacturer" nullable options={options.manufacturerOptions}
                        value={form.data.manufacturer_id} onChange={(v) => form.setData('manufacturer_id', v)} error={form.errors.manufacturer_id} />
                    <TextField id="model_number" label="Model number"
                        value={form.data.model_number} onChange={(v) => form.setData('model_number', v)} error={form.errors.model_number} />
                    <ComboboxField id="location_id" label="Location" nullable options={options.locationOptions}
                        value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
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
                <FormCardHeader title="Stock" description="An alert is shown once the quantity drops to the minimum." />
                <FormCardContent className="md:grid-cols-2">
                    <NumberField id="qty" label="Quantity" required step="1" min="0"
                        value={form.data.qty} onChange={(v) => form.setData('qty', v)} error={form.errors.qty} />
                    <NumberField id="min_amt" label="Minimum quantity" step="1" min="0"
                        value={form.data.min_amt} onChange={(v) => form.setData('min_amt', v)} error={form.errors.min_amt} />
                    <SwitchField id="requestable" label="Requestable"
                        description="Users may request this accessory."
                        checked={form.data.requestable} onChange={(v) => form.setData('requestable', v)} error={form.errors.requestable} />
                    <SwitchField id="archived" label="Archived"
                        description="No longer in use; kept for its history."
                        checked={form.data.archived} onChange={(v) => form.setData('archived', v)} error={form.errors.archived} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Purchasing" />
                <FormCardContent className="md:grid-cols-2">
                    <ComboboxField id="default_supplier_id" label="Default supplier" nullable options={options.supplierOptions}
                        value={form.data.default_supplier_id} onChange={(v) => form.setData('default_supplier_id', v)} error={form.errors.default_supplier_id} />
                    <NumberField id="default_purchase_cost" label="Default purchase cost" min="0"
                        value={form.data.default_purchase_cost} onChange={(v) => form.setData('default_purchase_cost', v)} error={form.errors.default_purchase_cost} />
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
