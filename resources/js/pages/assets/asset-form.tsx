import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { TextareaField } from '@/components/form/textarea-field';
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { UserPicker } from '@/components/form/user-picker';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { SwitchField } from '@/components/form/switch-field';
import { FileField } from '@/components/form/file-field';
import { TagsInput } from '@/components/form/tags-input';
import { FormCard, FormCardContent, FormCardHeader } from '@/components/form/form-card';
import { CustomFieldInput, type CustomFieldDefinition, type CustomFieldValue } from '@/components/form/custom-field-input';

type Option = { value: string; label: string };
export type AssigneeType = 'user' | 'location' | 'asset';

export const ASSIGNEE_TYPE_OPTIONS: { value: AssigneeType; label: string }[] = [
    { value: 'user', label: 'User' },
    { value: 'location', label: 'Location' },
    { value: 'asset', label: 'Asset' },
];

export interface AssetOptions {
    statusOptions: Option[]; defaultStatusId?: string | null;
    locationOptions: Option[]; rtdLocationOptions: Option[]; supplierOptions: Option[]; modelOptions: Option[];
    assigneeOptions: Record<AssigneeType, Option[]>;
    userCreateUrl: string;
    /** Model id => the custom fields of its fieldset. */
    customFieldsByModel?: Record<string, CustomFieldDefinition[]>;
}
export interface AssetInitial {
    id: string; name: string | null; asset_tag: string | null; status_id: string | null; model_id: string | null;
    serial: string | null; assignee_type: AssigneeType | null; assignee_id: string | null;
    location_id: string | null; rtd_location_id: string | null; supplier_id: string | null;
    purchase_date: string | null; purchase_cost: string; order_number: string | null; warranty_months: number | null;
    asset_eol_date: string | null; eol_explicit: boolean; depreciate: boolean | null;
    physical: boolean; requestable: boolean; byod: boolean | null; archived: boolean | null;
    expected_checkin: string | null; last_audit_date: string | null; next_audit_date: string | null;
    notes: string | null; image_url: string | null; tags: string[];
    custom_fields?: Record<string, CustomFieldValue>;
}
interface Props { initial?: AssetInitial; options: AssetOptions; submitUrl: string; method: 'post' | 'put'; forceId?: string | null; }

export function AssetForm({ initial, options, submitUrl, method, forceId }: Props) {
    const form = useForm({
        id: forceId ?? '',
        name: initial?.name ?? '',
        asset_tag: initial?.asset_tag ?? '',
        status_id: initial ? (initial.status_id ?? '') : (options.defaultStatusId ?? ''),
        model_id: initial?.model_id ?? '',
        serial: initial?.serial ?? '',
        assignee_type: (initial?.assignee_type ?? '') as AssigneeType | '',
        assignee_id: initial?.assignee_id ?? '',
        location_id: initial?.location_id ?? '',
        rtd_location_id: initial?.rtd_location_id ?? '',
        supplier_id: initial?.supplier_id ?? '',
        purchase_date: initial?.purchase_date ?? '',
        purchase_cost: initial?.purchase_cost ?? '',
        order_number: initial?.order_number ?? '',
        warranty_months: initial?.warranty_months == null ? '' : String(initial.warranty_months),
        eol_explicit: initial?.eol_explicit ?? false,
        asset_eol_date: initial?.asset_eol_date ?? '',
        depreciate: initial?.depreciate ?? false,
        physical: initial?.physical ?? true,
        requestable: initial?.requestable ?? false,
        byod: initial?.byod ?? false,
        archived: initial?.archived ?? false,
        expected_checkin: initial?.expected_checkin ?? '',
        last_audit_date: initial?.last_audit_date ?? '',
        next_audit_date: initial?.next_audit_date ?? '',
        notes: initial?.notes ?? '',
        tags: initial?.tags ?? [],
        custom_fields: initial?.custom_fields ?? {} as Record<string, CustomFieldValue>,
        image: null as File | null,
        remove_image: false,
    });
    const customFields = options.customFieldsByModel?.[form.data.model_id] ?? [];
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // A file only travels as multipart, and Inertia cannot send that over
        // PUT, so updates are method-spoofed. Without an image change the
        // form stays JSON, which keeps custom field values (arrays, nulls)
        // exactly as they are.
        const withFile = form.data.image !== null || form.data.remove_image;
        form.transform((data) => ({
            ...data,
            ...(withFile ? { remove_image: data.remove_image ? '1' : '0' } : {}),
            ...(method === 'put' ? { _method: 'put' } : {}),
        }));
        form.post(submitUrl, { forceFormData: withFile });
    };

    const changeAssigneeType = (type: string) =>
        form.setData((d) => ({ ...d, assignee_type: type as AssigneeType | '', assignee_id: '' }));

    return (
        <form onSubmit={submit} className="space-y-6">
            <FormCard>
                <FormCardHeader title="General" description={forceId && (
                    <>Asset-ID: <span className="font-mono">{forceId}</span></>
                )} />
                <FormCardContent className="md:grid-cols-2">
                    <TextField id="name" label="Name"
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <TextField id="asset_tag" label="Asset tag"
                        value={form.data.asset_tag} onChange={(v) => form.setData('asset_tag', v)} error={form.errors.asset_tag} />
                    <SelectField id="status_id" label="Status" required options={options.statusOptions}
                        value={form.data.status_id} onChange={(v) => form.setData('status_id', v)} error={form.errors.status_id} />
                    <ComboboxField id="model_id" label="Model" nullable options={options.modelOptions}
                        value={form.data.model_id} onChange={(v) => form.setData('model_id', v)} error={form.errors.model_id} />
                    <TextField id="serial" label="Serial"
                        value={form.data.serial} onChange={(v) => form.setData('serial', v)} error={form.errors.serial} />
                    <FileField id="image" label="Image"
                        value={form.data.image}
                        onChange={(file) => form.setData((d) => ({ ...d, image: file, remove_image: false }))}
                        accept="image/png,image/jpeg,image/webp"
                        hint="PNG, JPEG or WebP, up to 2 MB."
                        currentUrl={initial?.image_url ?? null}
                        currentRemoved={form.data.remove_image}
                        onRemoveCurrent={() => form.setData('remove_image', true)}
                        error={form.errors.image} />
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
                    <SelectField id="assignee_type" label="Assigned to" nullable options={ASSIGNEE_TYPE_OPTIONS}
                        value={form.data.assignee_type} onChange={changeAssigneeType} error={form.errors.assignee_type} />
                    {form.data.assignee_type === 'user' && (
                        <UserPicker id="assignee_id" label="User" options={options.assigneeOptions.user} createUrl={options.userCreateUrl}
                            value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                    )}
                    {(form.data.assignee_type === 'location' || form.data.assignee_type === 'asset') && (
                        <ComboboxField id="assignee_id" label={form.data.assignee_type === 'location' ? 'Location' : 'Asset'} required
                            options={options.assigneeOptions[form.data.assignee_type]}
                            value={form.data.assignee_id} onChange={(v) => form.setData('assignee_id', v)} error={form.errors.assignee_id} />
                    )}
                    {form.data.assignee_type === '' && <div className="hidden md:block" />}
                    <ComboboxField id="location_id" label="Location" nullable options={options.locationOptions}
                        value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                    <ComboboxField id="rtd_location_id" label="Default location" nullable options={options.rtdLocationOptions}
                        value={form.data.rtd_location_id} onChange={(v) => form.setData('rtd_location_id', v)} error={form.errors.rtd_location_id} />
                    <DateField id="expected_checkin" label="Expected check-in"
                        value={form.data.expected_checkin} onChange={(v) => form.setData('expected_checkin', v)} error={form.errors.expected_checkin} />
                    <SwitchField id="requestable" label="Requestable" description="Users may request this asset."
                        checked={form.data.requestable} onChange={(v) => form.setData('requestable', v)} error={form.errors.requestable} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Purchase & warranty" />
                <FormCardContent className="md:grid-cols-2">
                    <ComboboxField id="supplier_id" label="Supplier" nullable options={options.supplierOptions}
                        value={form.data.supplier_id} onChange={(v) => form.setData('supplier_id', v)} error={form.errors.supplier_id} />
                    <TextField id="order_number" label="Order number"
                        value={form.data.order_number} onChange={(v) => form.setData('order_number', v)} error={form.errors.order_number} />
                    <DateField id="purchase_date" label="Purchase date"
                        value={form.data.purchase_date} onChange={(v) => form.setData('purchase_date', v)} error={form.errors.purchase_date} />
                    <NumberField id="purchase_cost" label="Purchase cost" min="0"
                        value={form.data.purchase_cost} onChange={(v) => form.setData('purchase_cost', v)} error={form.errors.purchase_cost} />
                    <NumberField id="warranty_months" label="Warranty (months)" step="1" min="0"
                        value={form.data.warranty_months} onChange={(v) => form.setData('warranty_months', v)} error={form.errors.warranty_months} />
                    <SwitchField id="depreciate" label="Depreciate"
                        checked={form.data.depreciate} onChange={(v) => form.setData('depreciate', v)} error={form.errors.depreciate} />
                </FormCardContent>
            </FormCard>

            <FormCard>
                <FormCardHeader title="Lifecycle" description="Without a date set by hand, the EOL date is the purchase date plus the model's EOL months." />
                <FormCardContent className="md:grid-cols-2">
                    <SwitchField id="eol_explicit" label="Set EOL date by hand"
                        checked={form.data.eol_explicit} onChange={(v) => form.setData('eol_explicit', v)} error={form.errors.eol_explicit} />
                    {form.data.eol_explicit ? (
                        <DateField id="asset_eol_date" label="EOL date"
                            value={form.data.asset_eol_date} onChange={(v) => form.setData('asset_eol_date', v)} error={form.errors.asset_eol_date} />
                    ) : <div className="hidden md:block" />}
                    <DateField id="last_audit_date" label="Last audit"
                        value={form.data.last_audit_date} onChange={(v) => form.setData('last_audit_date', v)} error={form.errors.last_audit_date} />
                    <DateField id="next_audit_date" label="Next audit"
                        value={form.data.next_audit_date} onChange={(v) => form.setData('next_audit_date', v)} error={form.errors.next_audit_date} />
                    <SwitchField id="physical" label="Physical" description="A physical device, not e.g. a virtual machine."
                        checked={form.data.physical} onChange={(v) => form.setData('physical', v)} error={form.errors.physical} />
                    <SwitchField id="byod" label="BYOD" description="Owned by the employee (bring your own device)."
                        checked={form.data.byod} onChange={(v) => form.setData('byod', v)} error={form.errors.byod} />
                    <SwitchField id="archived" label="Archived" description="No longer in use; kept for its history."
                        checked={form.data.archived} onChange={(v) => form.setData('archived', v)} error={form.errors.archived} />
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
