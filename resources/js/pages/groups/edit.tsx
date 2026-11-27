import AppLayout from '@/layouts/app-layout';
import type { CatalogueSection } from '@/components/permissions/permission-matrix';
import { GroupForm } from './group-form';
import type { Group } from './types';

export default function EditGroup({ group, catalogue }: { group: Group; catalogue: CatalogueSection[] }) {
    return (
        <AppLayout title="Edit group" breadcrumbs={[{ label: 'Settings', href: '/app/settings' }, { label: 'Groups', href: '/app/groups' }, { label: group.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit group</h1>
            <GroupForm initial={group} catalogue={catalogue} submitUrl={`/app/groups/${group.id}`} method="put" />
        </AppLayout>
    );
}
