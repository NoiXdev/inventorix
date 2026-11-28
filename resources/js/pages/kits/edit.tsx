import AppLayout from '@/layouts/app-layout';
import { KitForm, type KitInitial, type KitOptions } from './kit-form';

export default function EditKit({ kit, ...options }: KitOptions & { kit: KitInitial }) {
    return (
        <AppLayout title="Edit kit" breadcrumbs={[{ label: 'Kits', href: '/app/kits' }, { label: kit.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit kit</h1>
            <KitForm initial={kit} options={options} submitUrl={`/app/kits/${kit.id}`} method="put" />
        </AppLayout>
    );
}
