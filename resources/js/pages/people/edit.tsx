import AppLayout from '@/layouts/app-layout';
import { PersonForm } from './person-form';

interface Props { person: { id: string; firstname: string; lastname: string; email: string | null } }

export default function EditPerson({ person }: Props) {
    return (
        <AppLayout title="Edit person" breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: 'Edit' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit person</h1>
            <PersonForm initial={person} submitUrl={`/app/people/${person.id}`} method="put" />
        </AppLayout>
    );
}
