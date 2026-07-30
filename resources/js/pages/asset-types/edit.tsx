import AppLayout from '@/layouts/app-layout';
import { AssetTypeForm } from './asset-type-form';

export default function EditAssetType({ assetType }: { assetType: { id: string; name: string } }) {
    return (
        <AppLayout title="Edit asset type" breadcrumbs={[{ label: 'Asset types', href: '/app/asset-types' }, { label: assetType.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset type</h1>
            <AssetTypeForm initial={assetType} submitUrl={`/app/asset-types/${assetType.id}`} method="put" />
        </AppLayout>
    );
}
