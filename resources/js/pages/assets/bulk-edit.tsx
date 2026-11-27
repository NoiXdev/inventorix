import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { TextField } from '@/components/form/text-field';
import { FormError } from '@/components/form/form-error';
import { BulkAssetPicker, assetIdsError, type BulkAsset, type SkippedAsset } from '@/components/assets/bulk-asset-picker';

type Option = { value: string; label: string };

interface Props {
    availableAssets: BulkAsset[];
    preselectedIds: string[];
    skipped: SkippedAsset[];
    statusOptions: Option[];
    modelOptions: Option[];
    locationOptions: Option[];
    supplierOptions: Option[];
    maxAssets: number;
}

type Field = 'status_id' | 'model_id' | 'location_id' | 'rtd_location_id' | 'supplier_id' | 'purchase_date' | 'purchase_cost'
    | 'order_number' | 'warranty_months' | 'next_audit_date' | 'expected_checkin' | 'notes';

const YES_NO: Option[] = [{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }];

/** A field that may also be emptied: its "Clear" checkbox empties it on every asset. */
function Clearable({ field, label, cleared, onClear, children }: { field: Field; label: string; cleared: boolean; onClear: (field: Field, on: boolean) => void; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            {/* A disabled fieldset also keeps the keyboard out of a cleared field. */}
            <fieldset disabled={cleared} className={cleared ? 'opacity-50' : undefined}>{children}</fieldset>
            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                <Checkbox checked={cleared} onCheckedChange={(v) => onClear(field, v === true)} aria-label={`Clear ${label}`} />
                Clear on all selected assets
            </label>
        </div>
    );
}

/**
 * Edit several assets at once (Snipe-IT's bulk edit): a field left empty
 * stays as it is on each asset, "Clear" empties it.
 */
export default function BulkEdit({ availableAssets, preselectedIds, skipped, statusOptions, modelOptions, locationOptions, supplierOptions, maxAssets }: Props) {
    const form = useForm({
        asset_ids: preselectedIds,
        status_id: '', model_id: '', location_id: '', rtd_location_id: '', supplier_id: '',
        purchase_date: '', purchase_cost: '', order_number: '', warranty_months: '',
        next_audit_date: '', expected_checkin: '', notes: '',
        requestable: '', archived: '', depreciate: '',
        clear: [] as Field[],
    });
    const count = form.data.asset_ids.length;
    const cleared = (f: Field) => form.data.clear.includes(f);
    const setClear = (f: Field, on: boolean) => {
        form.setData('clear', on ? [...form.data.clear.filter((x) => x !== f), f] : form.data.clear.filter((x) => x !== f));
        if (on) form.setData(f, '');
    };
    const set = (f: Field) => (v: string) => form.setData(f, v);
    const clearable = (f: Field, label: string, control: React.ReactNode) => (
        <Clearable field={f} label={label} cleared={cleared(f)} onClear={setClear}>{control}</Clearable>
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/app/assets/bulk-edit');
    };

    return (
        <AppLayout title="Bulk edit" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'Bulk edit' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Bulk edit</h1>
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[2fr_3fr]">
                <BulkAssetPicker
                    assets={availableAssets} value={form.data.asset_ids} onChange={(ids) => form.setData('asset_ids', ids)}
                    skipped={skipped} maxAssets={maxAssets} error={assetIdsError(form.errors)} showAssignee
                    description="Search by name, asset tag, serial or model, or scan a tag or serial."
                    notFoundHint="It may have been deleted."
                />

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Changes</CardTitle>
                        <CardDescription>
                            Only the fields you fill in change; empty fields stay as they are on each asset. Who an asset is assigned to changes through
                            check-out and check-in; checked-out assets keep a deployable or pending status. Assets checked out to an edited asset move to its new location.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField id="status_id" label="Status" nullable options={statusOptions}
                                value={form.data.status_id} onChange={set('status_id')} error={form.errors.status_id} />
                            {clearable('model_id', 'Model', <ComboboxField id="model_id" label="Model" nullable options={modelOptions}
                                value={form.data.model_id} onChange={set('model_id')} error={form.errors.model_id} />)}
                            {clearable('location_id', 'Location', <ComboboxField id="location_id" label="Location" nullable options={locationOptions}
                                value={form.data.location_id} onChange={set('location_id')} error={form.errors.location_id} />)}
                            {clearable('rtd_location_id', 'Default location', <ComboboxField id="rtd_location_id" label="Default location" nullable options={locationOptions}
                                value={form.data.rtd_location_id} onChange={set('rtd_location_id')} error={form.errors.rtd_location_id} />)}
                            {clearable('supplier_id', 'Supplier', <ComboboxField id="supplier_id" label="Supplier" nullable options={supplierOptions}
                                value={form.data.supplier_id} onChange={set('supplier_id')} error={form.errors.supplier_id} />)}
                            {clearable('order_number', 'Order number', <TextField id="order_number" label="Order number"
                                value={form.data.order_number} onChange={set('order_number')} error={form.errors.order_number} />)}
                            {clearable('purchase_date', 'Purchase date', <DateField id="purchase_date" label="Purchase date"
                                value={form.data.purchase_date} onChange={set('purchase_date')} error={form.errors.purchase_date} />)}
                            {clearable('purchase_cost', 'Purchase cost', <NumberField id="purchase_cost" label="Purchase cost"
                                value={form.data.purchase_cost} onChange={set('purchase_cost')} error={form.errors.purchase_cost} />)}
                            {clearable('warranty_months', 'Warranty (months)', <NumberField id="warranty_months" label="Warranty (months)" step="1" min="0"
                                value={form.data.warranty_months} onChange={set('warranty_months')} error={form.errors.warranty_months} />)}
                            {clearable('next_audit_date', 'Next audit', <DateField id="next_audit_date" label="Next audit"
                                value={form.data.next_audit_date} onChange={set('next_audit_date')} error={form.errors.next_audit_date} />)}
                            {clearable('expected_checkin', 'Expected check-in', <DateField id="expected_checkin" label="Expected check-in"
                                value={form.data.expected_checkin} onChange={set('expected_checkin')} error={form.errors.expected_checkin} />)}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <SelectField id="requestable" label="Requestable" nullable options={YES_NO}
                                value={form.data.requestable} onChange={(v) => form.setData('requestable', v)} error={form.errors.requestable} />
                            <SelectField id="archived" label="Archived" nullable options={YES_NO}
                                value={form.data.archived} onChange={(v) => form.setData('archived', v)} error={form.errors.archived} />
                            <SelectField id="depreciate" label="Depreciate" nullable options={YES_NO}
                                value={form.data.depreciate} onChange={(v) => form.setData('depreciate', v)} error={form.errors.depreciate} />
                        </div>
                        {clearable('notes', 'Notes', (
                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes (replace)</Label>
                                <Textarea id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                                <FormError message={form.errors.notes} />
                            </div>
                        ))}
                        <FormError message={(form.errors as Record<string, string>).changes} />
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href="/app/assets">Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || count === 0 || count > maxAssets}>Update {count || ''}</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
