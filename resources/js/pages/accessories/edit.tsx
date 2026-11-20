import AppLayout from '@/layouts/app-layout';
import { AccessoryForm, type AccessoryInitial, type AccessoryOptions } from './accessory-form';

export default function EditAccessory({ accessory, ...options }: AccessoryOptions & { accessory: AccessoryInitial }) {
    return (
        <AppLayout title="Edit accessory" breadcrumbs={[{ label: 'Accessories', href: '/app/accessories' }, { label: accessory.name ?? 'Accessory' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit accessory</h1>
            <AccessoryForm initial={accessory} options={options} submitUrl={`/app/accessories/${accessory.id}`} method="put" />
        </AppLayout>
    );
}
