import AppLayout from '@/layouts/app-layout';
import { AccessoryForm, type AccessoryOptions } from './accessory-form';

export default function CreateAccessory(options: AccessoryOptions) {
    return (
        <AppLayout title="New accessory" breadcrumbs={[{ label: 'Accessories', href: '/app/accessories' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New accessory</h1>
            <AccessoryForm options={options} submitUrl="/app/accessories" method="post" />
        </AppLayout>
    );
}
