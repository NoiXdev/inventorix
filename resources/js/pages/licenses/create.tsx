import AppLayout from '@/layouts/app-layout';
import { LicenseForm, type LicenseOptions } from './license-form';

export default function CreateLicense(options: LicenseOptions) {
    return (
        <AppLayout title="New license" breadcrumbs={[{ label: 'Licenses', href: '/app/licenses' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New license</h1>
            <LicenseForm options={options} submitUrl="/app/licenses" method="post" />
        </AppLayout>
    );
}
