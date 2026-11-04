import AppLayout from '@/layouts/app-layout';
import { AssetModelForm, type AssetModelInitial, type AssetModelOptions } from './asset-model-form';

export default function EditAssetModel({ assetModel, ...options }: AssetModelOptions & { assetModel: AssetModelInitial }) {
    return (
        <AppLayout title="Edit asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: assetModel.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset model</h1>
            <AssetModelForm {...options} initial={assetModel}
                submitUrl={`/app/asset-models/${assetModel.id}`} method="put" />
        </AppLayout>
    );
}
