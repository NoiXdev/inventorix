import AppLayout from '@/layouts/app-layout';
import { AssetModelForm } from './asset-model-form';

interface Props {
    assetModel: { id: string; name: string; manufacturer_id: string };
    manufacturerOptions: { value: string; label: string }[];
}

export default function EditAssetModel({ assetModel, manufacturerOptions }: Props) {
    return (
        <AppLayout title="Edit asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: assetModel.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset model</h1>
            <AssetModelForm initial={assetModel} manufacturerOptions={manufacturerOptions}
                submitUrl={`/app/asset-models/${assetModel.id}`} method="put" />
        </AppLayout>
    );
}
