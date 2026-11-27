import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SelectField } from '@/components/form/select-field';
import { ComboboxField } from '@/components/form/combobox-field';
import { DateField } from '@/components/form/date-field';
import { FormError } from '@/components/form/form-error';
import { BulkAssetPicker, assetIdsError, type BulkAsset, type SkippedAsset } from '@/components/assets/bulk-asset-picker';

type Option = { value: string; label: string };

interface Props {
    /** Assets that are checked out. */
    availableAssets: BulkAsset[];
    preselectedIds: string[];
    skipped: SkippedAsset[];
    statusOptions: Option[];
    locationOptions: Option[];
    /** Preselected status (storage), as for a single check-in. */
    checkinStatusId: string;
    maxAssets: number;
}

const today = () => new Date().toISOString().slice(0, 10);

export default function BulkCheckin({ availableAssets, preselectedIds, skipped, statusOptions, locationOptions, checkinStatusId, maxAssets }: Props) {
    const form = useForm({
        asset_ids: preselectedIds,
        status_id: statusOptions.some((s) => s.value === checkinStatusId) ? checkinStatusId : '',
        location_id: '',
        checkin_at: today(),
        note: '',
    });
    const count = form.data.asset_ids.length;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/assets/bulk-checkin');
    };

    return (
        <AppLayout title="Bulk check-in" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'Bulk check-in' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Bulk check-in</h1>
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <BulkAssetPicker
                    assets={availableAssets} value={form.data.asset_ids} onChange={(ids) => form.setData('asset_ids', ids)}
                    skipped={skipped} maxAssets={maxAssets} error={assetIdsError(form.errors)} showAssignee
                    description="Search by name, asset tag, serial, model or who has it, or scan a tag or serial. Only checked-out assets are offered."
                    notFoundHint="It may not be checked out."
                />

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Check in</CardTitle>
                        <CardDescription>All selected assets come back together. If one of them is not checked out, none are checked in.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <SelectField id="status_id" label="Status (— keeps the current one)" nullable options={statusOptions}
                            value={form.data.status_id} onChange={(v) => form.setData('status_id', v)} error={form.errors.status_id} />
                        <div>
                            <ComboboxField id="location_id" label="Location" nullable options={locationOptions}
                                value={form.data.location_id} onChange={(v) => form.setData('location_id', v)} error={form.errors.location_id} />
                            <p className="mt-1 text-xs text-muted-foreground">Empty: each asset goes to its default location, else stays where it is.</p>
                        </div>
                        <DateField id="checkin_at" label="Check-in date" value={form.data.checkin_at}
                            onChange={(v) => form.setData('checkin_at', v)} error={form.errors.checkin_at} />
                        <div className="space-y-2">
                            <Label htmlFor="note">Note</Label>
                            <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            <FormError message={form.errors.note} />
                        </div>
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href="/app/assets">Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || count === 0 || count > maxAssets}>Check in {count || ''}</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
