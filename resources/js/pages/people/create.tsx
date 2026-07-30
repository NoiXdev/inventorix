import AppLayout from '@/layouts/app-layout';
import { PersonForm } from './person-form';

export default function CreatePerson() {
    return (
        <AppLayout title="New person" breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New person</h1>
            <PersonForm submitUrl="/app/people" method="post" />
        </AppLayout>
    );
}
