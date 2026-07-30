import AppLayout from '@/layouts/app-layout';
import { PlaceForm } from './place-form';

export default function CreatePlace() {
    return (
        <AppLayout title="New place" breadcrumbs={[{ label: 'Places', href: '/app/places' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New place</h1>
            <PlaceForm submitUrl="/app/places" method="post" />
        </AppLayout>
    );
}
