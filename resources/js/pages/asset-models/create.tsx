import AppLayout from '@/layouts/app-layout';
import { AssetModelForm, type AssetModelOptions } from './asset-model-form';

export default function CreateAssetModel(options: AssetModelOptions) {
    return (
        <AppLayout title="New asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset model</h1>
            <AssetModelForm {...options} submitUrl="/app/asset-models" method="post" />
        </AppLayout>
    );
}
