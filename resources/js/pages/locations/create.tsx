import AppLayout from '@/layouts/app-layout';
import { LocationForm, type LocationOptions } from './location-form';

export default function CreateLocation(options: LocationOptions) {
    return (
        <AppLayout title="New location" breadcrumbs={[{ label: 'Locations', href: '/app/locations' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New location</h1>
            <LocationForm options={options} submitUrl="/app/locations" method="post" />
        </AppLayout>
    );
}
