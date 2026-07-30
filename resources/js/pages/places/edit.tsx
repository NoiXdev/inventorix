import AppLayout from '@/layouts/app-layout';
import { PlaceForm } from './place-form';

export default function EditPlace({ place }: { place: { id: string; name: string } }) {
    return (
        <AppLayout title="Edit place" breadcrumbs={[{ label: 'Places', href: '/app/places' }, { label: place.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit place</h1>
            <PlaceForm initial={place} submitUrl={`/app/places/${place.id}`} method="put" />
        </AppLayout>
    );
}
