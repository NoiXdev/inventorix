import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckoutFields, checkoutDefaults, type CheckoutData, type CheckoutOptions } from '@/components/assets/checkout-dialogs';
import { BulkAssetPicker, assetIdsError, type BulkAsset, type SkippedAsset } from '@/components/assets/bulk-asset-picker';

export type { BulkAsset };

interface Props {
    /** Assets that can be checked out: unassigned, with a deployable status. */
    availableAssets: BulkAsset[];
    /** Selected in the asset list and available. */
    preselectedIds: string[];
    /** Selected in the asset list but not available, with why. */
    skipped: SkippedAsset[];
    checkoutOptions: CheckoutOptions;
    maxAssets: number;
}

export default function BulkCheckout({ availableAssets, preselectedIds, skipped, checkoutOptions, maxAssets }: Props) {
    const form = useForm<CheckoutData & { asset_ids: string[] }>({
        ...checkoutDefaults(checkoutOptions),
        asset_ids: preselectedIds,
    });
    const count = form.data.asset_ids.length;
    const tooMany = count > maxAssets;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/assets/bulk-checkout');
    };

    return (
        <AppLayout title="Bulk checkout" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'Bulk checkout' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Bulk checkout</h1>
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <BulkAssetPicker
                    assets={availableAssets} value={form.data.asset_ids} onChange={(ids) => form.setData('asset_ids', ids)}
                    skipped={skipped} maxAssets={maxAssets} error={assetIdsError(form.errors)}
                    description="Search by name, asset tag, serial or model, or scan a tag or serial. Only assets that are not checked out and have a deployable status are offered."
                    notFoundHint="It may be checked out already or have a status that is not deployable."
                />

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Check out to</CardTitle>
                        <CardDescription>All selected assets go to the same user, location or asset. If one of them cannot be checked out, none are.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <CheckoutFields data={form.data} setData={form.setData} errors={form.errors} options={checkoutOptions} />
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" asChild><Link href="/app/assets">Cancel</Link></Button>
                            <Button type="submit" disabled={form.processing || count === 0 || tooMany}>
                                Check out {count || ''}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
