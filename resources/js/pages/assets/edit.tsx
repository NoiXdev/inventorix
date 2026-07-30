import AppLayout from '@/layouts/app-layout';
import { AssetForm, type AssetOptions, type AssetInitial } from './asset-form';

interface Props extends AssetOptions { asset: AssetInitial; }

export default function EditAsset({ asset, ...options }: Props) {
    return (
        <AppLayout title="Edit asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: asset.serial_number ?? 'Asset' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset</h1>
            <AssetForm initial={asset} options={options} submitUrl={`/app/assets/${asset.id}`} method="put" />
        </AppLayout>
    );
}
