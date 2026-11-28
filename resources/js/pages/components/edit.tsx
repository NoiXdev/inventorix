import AppLayout from '@/layouts/app-layout';
import { ComponentForm, type ComponentInitial, type ComponentOptions } from './component-form';

export default function EditComponent({ component, ...options }: ComponentOptions & { component: ComponentInitial }) {
    return (
        <AppLayout title="Edit component" breadcrumbs={[{ label: 'Components', href: '/app/components' }, { label: component.name ?? 'Component' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit component</h1>
            <ComponentForm initial={component} options={options} submitUrl={`/app/components/${component.id}`} method="put" />
        </AppLayout>
    );
}
