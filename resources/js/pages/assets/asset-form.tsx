import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { UserPicker } from '@/components/form/user-picker';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { TagsInput } from '@/components/form/tags-input';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { CustomFieldInput, type CustomFieldDefinition, type CustomFieldValue } from '@/components/form/custom-field-input';

type Option = { value: string; label: string };
export interface AssetOptions {
    stateOptions: Option[]; buyTypeOptions: Option[]; assetTypeOptions: Option[];
    ownerOptions: Option[]; placeOptions: Option[]; modelOptions: Option[];
    userCreateUrl: string;
    /** Model id => the custom fields of its fieldset. */
    customFieldsByModel?: Record<string, CustomFieldDefinition[]>;
}
export interface AssetInitial {
    id: string; state: string; asset_type_id: string; owner_id: string | null; place_id: string | null;
    model_id: string | null; serial_number: string | null; buy_date: string | null; guarantee_end: string | null;
    buy_type: string | null; buy_price: string; invoice: string | null; tags: string[];
    custom_fields?: Record<string, CustomFieldValue>;
}
interface Props { initial?: AssetInitial; options: AssetOptions; submitUrl: string; method: 'post' | 'put'; forceId?: string | null; }

export function AssetForm({ initial, options, submitUrl, method, forceId }: Props) {
    const form = useForm({
        id: forceId ?? '',
        state: initial?.state ?? '',
        asset_type_id: initial?.asset_type_id ?? '',
        owner_id: initial?.owner_id ?? '',
        place_id: initial?.place_id ?? '',
        model_id: initial?.model_id ?? '',
        serial_number: initial?.serial_number ?? '',
        buy_date: initial?.buy_date ?? '',
        guarantee_end: initial?.guarantee_end ?? '',
        buy_type: initial?.buy_type ?? '',
        buy_price: initial?.buy_price ?? '',
        invoice: initial?.invoice ?? '',
        tags: initial?.tags ?? [],
        custom_fields: initial?.custom_fields ?? {} as Record<string, CustomFieldValue>,
    });
    const customFields = options.customFieldsByModel?.[form.data.model_id] ?? [];
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" description={forceId && (
                    <>Asset-ID: <span className="font-mono">{forceId}</span></>
                )} />
                <FormCardContent className="md:grid-cols-2">
                    <SelectField id="state" label="State" required options={options.stateOptions}
                        value={form.data.state} onChange={(v) => form.setData('state', v)} error={form.errors.state} />
                    <SelectField id="asset_type_id" label="Asset type" required options={options.assetTypeOptions}
                        value={form.data.asset_type_id} onChange={(v) => form.setData('asset_type_id', v)} error={form.errors.asset_type_id} />
                    <SelectField id="model_id" label="Model" nullable options={options.modelOptions}
                        value={form.data.model_id} onChange={(v) => form.setData('model_id', v)} error={form.errors.model_id} />
                    <TextField id="serial_number" label="Serial number"
                        value={form.data.serial_number} onChange={(v) => form.setData('serial_number', v)} error={form.errors.serial_number} />
                    <div className="md:col-span-2">
                        <TagsInput id="tags" label="Tags" value={form.data.tags} onChange={(v) => form.setData('tags', v)} error={form.errors.tags} />
                    </div>
                </FormCardContent>
            </FormCard>

            {customFields.length > 0 && (
                <FormCard>
                    <FormCardHeader title="Custom fields" description="Defined by the model's fieldset." />
                    <FormCardContent className="md:grid-cols-2">
                        {customFields.map((field) => (
                            <CustomFieldInput key={field.db_column} field={field}
                                value={form.data.custom_fields[field.db_column] ?? null}
                                onChange={(v) => form.setData('custom_fields', { ...form.data.custom_fields, [field.db_column]: v })}
                                error={errors[`custom_fields.${field.db_column}`] ?? errors[`custom_fields.${field.db_column}.0`]} />
                        ))}
                    </FormCardContent>
                </FormCard>
            )}

            <FormCard>
                <FormCardHeader title="Assignment" />
                <FormCardContent className="md:grid-cols-2">
                    <UserPicker id="owner_id" label="Owner" options={options.ownerOptions} createUrl={options.userCreateUrl}
                        value={form.data.owner_id} onChange={(v) => form.setData('owner_id', v)} error={form.errors.owner_id} />
                    <SelectField id="place_id" label="Place" nullable options={options.placeOptions}
                        value={form.data.place_id} onChange={(v) => form.setData('place_id', v)} error={form.errors.place_id} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Purchase" />
                <FormCardContent className="md:grid-cols-2">
                    <DateField id="buy_date" label="Buy date"
                        value={form.data.buy_date} onChange={(v) => form.setData('buy_date', v)} error={form.errors.buy_date} />
                    <DateField id="guarantee_end" label="Guarantee end"
                        value={form.data.guarantee_end} onChange={(v) => form.setData('guarantee_end', v)} error={form.errors.guarantee_end} />
                    <SelectField id="buy_type" label="Buy type" nullable options={options.buyTypeOptions}
                        value={form.data.buy_type} onChange={(v) => form.setData('buy_type', v)} error={form.errors.buy_type} />
                    <NumberField id="buy_price" label="Buy price"
                        value={form.data.buy_price} onChange={(v) => form.setData('buy_price', v)} error={form.errors.buy_price} />
                    <TextField id="invoice" label="Invoice"
                        value={form.data.invoice} onChange={(v) => form.setData('invoice', v)} error={form.errors.invoice} />
                </FormCardContent>
            </FormCard>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
