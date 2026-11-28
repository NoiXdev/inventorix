import AppLayout from '@/layouts/app-layout';
import { DepartmentForm, type Option } from './department-form';

export default function CreateDepartment({ managerOptions, locationOptions }: { managerOptions: Option[]; locationOptions: Option[] }) {
    return (
        <AppLayout title="New department" breadcrumbs={[{ label: 'Departments', href: '/app/departments' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New department</h1>
            <DepartmentForm managerOptions={managerOptions} locationOptions={locationOptions} submitUrl="/app/departments" method="post" />
        </AppLayout>
    );
}
