import AppLayout from '@/layouts/app-layout';
import { AssetModelForm } from './asset-model-form';

export default function CreateAssetModel({ manufacturerOptions }: { manufacturerOptions: { value: string; label: string }[] }) {
    return (
        <AppLayout title="New asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset model</h1>
            <AssetModelForm manufacturerOptions={manufacturerOptions} submitUrl="/app/asset-models" method="post" />
        </AppLayout>
    );
}
