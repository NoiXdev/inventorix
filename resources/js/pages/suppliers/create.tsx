import AppLayout from '@/layouts/app-layout';
import { SupplierForm, type Option } from './supplier-form';

export default function CreateSupplier({ countryOptions }: { countryOptions: Option[] }) {
    return (
        <AppLayout title="New supplier" breadcrumbs={[{ label: 'Suppliers', href: '/app/suppliers' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New supplier</h1>
            <SupplierForm countryOptions={countryOptions} submitUrl="/app/suppliers" method="post" />
        </AppLayout>
    );
}
