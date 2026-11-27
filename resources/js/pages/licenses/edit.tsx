import AppLayout from '@/layouts/app-layout';
import { LicenseForm, type LicenseInitial, type LicenseOptions } from './license-form';

export default function EditLicense({ license, ...options }: LicenseOptions & { license: LicenseInitial }) {
    return (
        <AppLayout title={`Edit ${license.name}`} breadcrumbs={[{ label: 'Licenses', href: '/app/licenses' }, { label: license.name, href: `/app/licenses/${license.id}` }, { label: 'Edit' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit license</h1>
            <LicenseForm initial={license} options={options} submitUrl={`/app/licenses/${license.id}`} method="put" />
        </AppLayout>
    );
}
