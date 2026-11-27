import AppLayout from '@/layouts/app-layout';
import { ConsumableForm, type ConsumableInitial, type ConsumableOptions } from './consumable-form';

export default function EditConsumable({ consumable, ...options }: ConsumableOptions & { consumable: ConsumableInitial }) {
    return (
        <AppLayout title="Edit consumable" breadcrumbs={[{ label: 'Consumables', href: '/app/consumables' }, { label: consumable.name ?? 'Consumable' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit consumable</h1>
            <ConsumableForm initial={consumable} options={options} submitUrl={`/app/consumables/${consumable.id}`} method="put" />
        </AppLayout>
    );
}
