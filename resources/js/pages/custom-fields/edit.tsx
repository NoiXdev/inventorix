import AppLayout from '@/layouts/app-layout';
import { CustomFieldForm, type CustomFieldInitial, type CustomFieldOptions } from './custom-field-form';

export default function EditCustomField({ customField, ...options }: CustomFieldOptions & { customField: CustomFieldInitial }) {
    return (
        <AppLayout title="Edit custom field" breadcrumbs={[{ label: 'Custom fields', href: '/app/custom-fields' }, { label: customField.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit custom field</h1>
            <CustomFieldForm {...options} initial={customField} submitUrl={`/app/custom-fields/${customField.id}`} method="put" />
        </AppLayout>
    );
}
