import AppLayout from '@/layouts/app-layout';
import { CustomFieldsetForm, type AvailableField, type SelectedField } from './custom-fieldset-form';

export default function CreateCustomFieldset({ availableFields, initialFields }: { availableFields: AvailableField[]; initialFields: SelectedField[] }) {
    return (
        <AppLayout title="New fieldset" breadcrumbs={[{ label: 'Fieldsets', href: '/app/custom-fieldsets' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New fieldset</h1>
            <CustomFieldsetForm availableFields={availableFields} initialFields={initialFields} submitUrl="/app/custom-fieldsets" method="post" />
        </AppLayout>
    );
}
