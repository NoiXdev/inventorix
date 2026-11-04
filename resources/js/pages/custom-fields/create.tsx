import AppLayout from '@/layouts/app-layout';
import { CustomFieldForm, type CustomFieldOptions } from './custom-field-form';

export default function CreateCustomField(options: CustomFieldOptions) {
    return (
        <AppLayout title="New custom field" breadcrumbs={[{ label: 'Custom fields', href: '/app/custom-fields' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New custom field</h1>
            <CustomFieldForm {...options} submitUrl="/app/custom-fields" method="post" />
        </AppLayout>
    );
}
