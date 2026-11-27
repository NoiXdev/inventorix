import AppLayout from '@/layouts/app-layout';
import { ConsumableForm, type ConsumableOptions } from './consumable-form';

export default function CreateConsumable(options: ConsumableOptions) {
    return (
        <AppLayout title="New consumable" breadcrumbs={[{ label: 'Consumables', href: '/app/consumables' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New consumable</h1>
            <ConsumableForm options={options} submitUrl="/app/consumables" method="post" />
        </AppLayout>
    );
}
