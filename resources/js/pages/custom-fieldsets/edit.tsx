import AppLayout from '@/layouts/app-layout';
import { CustomFieldsetForm, type AvailableField, type CustomFieldsetInitial } from './custom-fieldset-form';

export default function EditCustomFieldset({ customFieldset, availableFields }: { customFieldset: CustomFieldsetInitial; availableFields: AvailableField[] }) {
    return (
        <AppLayout title="Edit fieldset" breadcrumbs={[{ label: 'Fieldsets', href: '/app/custom-fieldsets' }, { label: customFieldset.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit fieldset</h1>
            <CustomFieldsetForm initial={customFieldset} availableFields={availableFields} submitUrl={`/app/custom-fieldsets/${customFieldset.id}`} method="put" />
        </AppLayout>
    );
}
