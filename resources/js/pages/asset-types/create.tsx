import AppLayout from '@/layouts/app-layout';
import { AssetTypeForm } from './asset-type-form';

export default function CreateAssetType() {
    return (
        <AppLayout title="New asset type" breadcrumbs={[{ label: 'Asset types', href: '/app/asset-types' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset type</h1>
            <AssetTypeForm submitUrl="/app/asset-types" method="post" />
        </AppLayout>
    );
}
