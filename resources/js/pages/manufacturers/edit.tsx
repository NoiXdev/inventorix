import AppLayout from '@/layouts/app-layout';
import { ManufacturerForm } from './manufacturer-form';

export default function EditManufacturer({ manufacturer }: { manufacturer: { id: string; name: string } }) {
    return (
        <AppLayout title="Edit manufacturer" breadcrumbs={[{ label: 'Manufacturers', href: '/app/manufacturers' }, { label: manufacturer.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit manufacturer</h1>
            <ManufacturerForm initial={manufacturer} submitUrl={`/app/manufacturers/${manufacturer.id}`} method="put" />
        </AppLayout>
    );
}
