import AppLayout from '@/layouts/app-layout';
import { KitForm, type KitOptions } from './kit-form';

export default function CreateKit(options: KitOptions) {
    return (
        <AppLayout title="New kit" breadcrumbs={[{ label: 'Kits', href: '/app/kits' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New kit</h1>
            <KitForm options={options} submitUrl="/app/kits" method="post" />
        </AppLayout>
    );
}
