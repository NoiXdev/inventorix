import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { NumberField } from '@/components/form/number-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { FileField } from '@/components/form/file-field';
import { DateField } from '@/components/form/date-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';

export type Option = { value: string; label: string };

export interface ComponentOptions {
    categoryOptions: Option[];
    manufacturerOptions: Option[];
    supplierOptions: Option[];
    locationOptions: Option[];
}

export interface ComponentInitial {
    id: string;
    name: string | null;
    category_id: string | null;
    manufacturer_id: string | null;
    model_number: string | null;
    serial: string | null;
    purchase_date: string | null;
    order_number: string | null;
    location_id: string | null;
    supplier_id: string | null;
    qty: number;
    min_amt: number | null;
    purchase_cost: string | null;
    notes: string | null;
    image_url: string | null;
}

interface Props {
    initial?: ComponentInitial;
    options: ComponentOptions;
    submitUrl: string;
    method: 'post' | 'put';
}

const BOOLEANS = ['remove_image'] as const;

export function ComponentForm({ initial, options, submitUrl, method }: Props) {
    const form = useForm({
        name: initial?.name ?? '',
        category_id: initial?.category_id ?? '',
        manufacturer_id: initial?.manufacturer_id ?? '',
        model_number: initial?.model_number ?? '',
        serial: initial?.serial ?? '',
        purchase_date: initial?.purchase_date ?? '',
        order_number: initial?.order_number ?? '',
        location_id: initial?.location_id ?? '',
        supplier_id: initial?.supplier_id ?? '',
        qty: String(initial?.qty ?? 0),
        min_amt: initial?.min_amt == null ? '' : String(initial.min_amt),
        purchase_cost: initial?.purchase_cost ?? '',
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
                    <TextField id="serial" label="Serial number"
                        value={form.data.serial} onChange={(v) => form.setData('serial', v)} error={form.errors.serial} />
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
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Purchasing" />
                <FormCardContent className="md:grid-cols-2">
                    <ComboboxField id="supplier_id" label="Supplier" nullable options={options.supplierOptions}
                        value={form.data.supplier_id} onChange={(v) => form.setData('supplier_id', v)} error={form.errors.supplier_id} />
                    <NumberField id="purchase_cost" label="Purchase cost" min="0"
                        value={form.data.purchase_cost} onChange={(v) => form.setData('purchase_cost', v)} error={form.errors.purchase_cost} />
                    <DateField id="purchase_date" label="Purchase date"
                        value={form.data.purchase_date} onChange={(v) => form.setData('purchase_date', v)} error={form.errors.purchase_date} />
                    <TextField id="order_number" label="Order number"
                        value={form.data.order_number} onChange={(v) => form.setData('order_number', v)} error={form.errors.order_number} />
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
