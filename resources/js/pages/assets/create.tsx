import AppLayout from '@/layouts/app-layout';
import { AssetForm, type AssetOptions } from './asset-form';

export default function CreateAsset({ forceId, ...props }: AssetOptions & { forceId?: string | null }) {
    return (
        <AppLayout title="New asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset</h1>
            <AssetForm options={props} submitUrl="/app/assets" method="post" forceId={forceId} />
        </AppLayout>
    );
}
