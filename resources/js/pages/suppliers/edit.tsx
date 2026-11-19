import AppLayout from '@/layouts/app-layout';
import { SupplierForm, type SupplierInitial, type Option } from './supplier-form';

export default function EditSupplier({ supplier, countryOptions }: { supplier: SupplierInitial; countryOptions: Option[] }) {
    return (
        <AppLayout title="Edit supplier" breadcrumbs={[{ label: 'Suppliers', href: '/app/suppliers' }, { label: supplier.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit supplier</h1>
            <SupplierForm initial={supplier} countryOptions={countryOptions} submitUrl={`/app/suppliers/${supplier.id}`} method="put" />
        </AppLayout>
    );
}
