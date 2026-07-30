import AppLayout from '@/layouts/app-layout';
import { ManufacturerForm } from './manufacturer-form';

export default function CreateManufacturer() {
    return (
        <AppLayout title="New manufacturer" breadcrumbs={[{ label: 'Manufacturers', href: '/app/manufacturers' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New manufacturer</h1>
            <ManufacturerForm submitUrl="/app/manufacturers" method="post" />
        </AppLayout>
    );
}
