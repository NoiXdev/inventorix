import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { NumberField } from '@/components/form/number-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { SwitchField } from '@/components/form/switch-field';
import { DateField } from '@/components/form/date-field';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { useCan } from '@/hooks/use-can';

export type Option = { value: string; label: string };

export interface LicenseOptions {
    categoryOptions: Option[];
    manufacturerOptions: Option[];
    supplierOptions: Option[];
    depreciationOptions: Option[];
}

export interface LicenseInitial {
    id: string;
    name: string;
    product_key: string | null;
    seats: number;
    category_id: string | null;
    manufacturer_id: string | null;
    supplier_id: string | null;
    depreciation_id: string | null;
    licensed_to_name: string | null;
    licensed_to_email: string | null;
    reassignable: boolean;
    maintained: boolean;
    requestable: boolean;
    purchase_date: string | null;
    purchase_cost: string | null;
    order_number: string | null;
    purchase_order: string | null;
    expiration_date: string | null;
    termination_date: string | null;
    min_amt: number | null;
    notes: string | null;
}

interface Props {
    initial?: LicenseInitial;
    options: LicenseOptions;
    submitUrl: string;
    method: 'post' | 'put';
}

const str = (v: string | number | null | undefined) => (v == null ? '' : String(v));

export function LicenseForm({ initial, options, submitUrl, method }: Props) {
    const can = useCan();
    const canKeys = can('licenses.keys');
    const form = useForm({
        name: initial?.name ?? '',
        // Only sent by someone who may see product keys; others keep it as it is.
        ...(canKeys ? { product_key: initial?.product_key ?? '' } : {}),
        seats: str(initial?.seats ?? 1),
        category_id: initial?.category_id ?? '',
        manufacturer_id: initial?.manufacturer_id ?? '',
        supplier_id: initial?.supplier_id ?? '',
        depreciation_id: initial?.depreciation_id ?? '',
        licensed_to_name: initial?.licensed_to_name ?? '',
        licensed_to_email: initial?.licensed_to_email ?? '',
        reassignable: initial?.reassignable ?? true,
        maintained: initial?.maintained ?? false,
        requestable: initial?.requestable ?? false,
        purchase_date: initial?.purchase_date ?? '',
        purchase_cost: initial?.purchase_cost ?? '',
        order_number: initial?.order_number ?? '',
        purchase_order: initial?.purchase_order ?? '',
        expiration_date: initial?.expiration_date ?? '',
        termination_date: initial?.termination_date ?? '',
        min_amt: str(initial?.min_amt),
        notes: initial?.notes ?? '',
    });
    const errors = form.errors as Record<string, string>;
    const set = (key: keyof typeof form.data) => (v: string) => form.setData(key, v as never);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') form.put(submitUrl);
        else form.post(submitUrl);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name" required autoFocus value={form.data.name} onChange={set('name')} error={errors.name} />
                    <NumberField id="seats" label="Seats" required step="1" min="1" value={form.data.seats} onChange={set('seats')} error={errors.seats} />
                    {canKeys && (
                        <div className="md:col-span-2">
                            <TextareaField id="product_key" label="Product key" rows={2}
                                value={(form.data as { product_key?: string }).product_key ?? ''} onChange={set('product_key' as never)} error={errors.product_key} />
                        </div>
                    )}
                    <ComboboxField id="category_id" label="Category" nullable options={options.categoryOptions}
                        value={form.data.category_id} onChange={set('category_id')} error={errors.category_id} />
                    <ComboboxField id="manufacturer_id" label="Manufacturer" nullable options={options.manufacturerOptions}
                        value={form.data.manufacturer_id} onChange={set('manufacturer_id')} error={errors.manufacturer_id} />
                    <TextField id="licensed_to_name" label="Licensed to (name)" value={form.data.licensed_to_name} onChange={set('licensed_to_name')} error={errors.licensed_to_name} />
                    <TextField id="licensed_to_email" label="Licensed to (email)" value={form.data.licensed_to_email} onChange={set('licensed_to_email')} error={errors.licensed_to_email} />
                    <SwitchField id="reassignable" label="Reassignable" description="A seat that comes back can be checked out again. Otherwise it is used up."
                        checked={form.data.reassignable} onChange={(v) => form.setData('reassignable', v)} error={errors.reassignable} />
                    <SwitchField id="maintained" label="Maintained" description="Covered by a maintenance or subscription contract."
                        checked={form.data.maintained} onChange={(v) => form.setData('maintained', v)} error={errors.maintained} />
                    <NumberField id="min_amt" label="Minimum free seats" step="1" min="0" value={form.data.min_amt} onChange={set('min_amt')} error={errors.min_amt} />
                    <SwitchField id="requestable" label="Requestable" description="Users may request a seat."
                        checked={form.data.requestable} onChange={(v) => form.setData('requestable', v)} error={errors.requestable} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Purchasing and term" />
                <FormCardContent className="md:grid-cols-2">
                    <ComboboxField id="supplier_id" label="Supplier" nullable options={options.supplierOptions}
                        value={form.data.supplier_id} onChange={set('supplier_id')} error={errors.supplier_id} />
                    <ComboboxField id="depreciation_id" label="Depreciation" nullable options={options.depreciationOptions}
                        value={form.data.depreciation_id} onChange={set('depreciation_id')} error={errors.depreciation_id} />
                    <DateField id="purchase_date" label="Purchase date" value={form.data.purchase_date} onChange={set('purchase_date')} error={errors.purchase_date} />
                    <NumberField id="purchase_cost" label="Purchase cost" min="0" value={form.data.purchase_cost} onChange={set('purchase_cost')} error={errors.purchase_cost} />
                    <TextField id="order_number" label="Order number" value={form.data.order_number} onChange={set('order_number')} error={errors.order_number} />
                    <TextField id="purchase_order" label="Purchase order" value={form.data.purchase_order} onChange={set('purchase_order')} error={errors.purchase_order} />
                    <DateField id="expiration_date" label="Expiration date" value={form.data.expiration_date} onChange={set('expiration_date')} error={errors.expiration_date} />
                    <DateField id="termination_date" label="Termination date" value={form.data.termination_date} onChange={set('termination_date')} error={errors.termination_date} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Notes" />
                <FormCardContent>
                    <TextareaField id="notes" label="Notes" rows={5} value={form.data.notes} onChange={set('notes')} error={errors.notes} />
                </FormCardContent>
            </FormCard>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>{method === 'put' ? 'Save' : 'Create license'}</Button>
                <Button type="button" variant="ghost" asChild><Link href={initial ? `/app/licenses/${initial.id}` : '/app/licenses'}>Cancel</Link></Button>
            </div>
        </form>
    );
}
