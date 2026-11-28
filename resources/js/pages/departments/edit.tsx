import AppLayout from '@/layouts/app-layout';
import { DepartmentForm, type DepartmentInitial, type Option } from './department-form';

export default function EditDepartment({ department, managerOptions, locationOptions }: { department: DepartmentInitial; managerOptions: Option[]; locationOptions: Option[] }) {
    return (
        <AppLayout title="Edit department" breadcrumbs={[{ label: 'Departments', href: '/app/departments' }, { label: department.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit department</h1>
            <DepartmentForm initial={department} managerOptions={managerOptions} locationOptions={locationOptions}
                submitUrl={`/app/departments/${department.id}`} method="put" />
        </AppLayout>
    );
}
