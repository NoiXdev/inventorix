import AppLayout from '@/layouts/app-layout';
import { ComponentForm, type ComponentOptions } from './component-form';

export default function CreateComponent(options: ComponentOptions) {
    return (
        <AppLayout title="New component" breadcrumbs={[{ label: 'Components', href: '/app/components' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New component</h1>
            <ComponentForm options={options} submitUrl="/app/components" method="post" />
        </AppLayout>
    );
}
