import AppLayout from '@/layouts/app-layout';
import { LocationForm, type LocationInitial, type LocationOptions } from './location-form';

export default function EditLocation({ location, ...options }: LocationOptions & { location: LocationInitial }) {
    return (
        <AppLayout title="Edit location" breadcrumbs={[{ label: 'Locations', href: '/app/locations' }, { label: location.name ?? 'Location' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit location</h1>
            <LocationForm initial={location} options={options} submitUrl={`/app/locations/${location.id}`} method="put" />
        </AppLayout>
    );
}
