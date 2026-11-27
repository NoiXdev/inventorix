import AppLayout from '@/layouts/app-layout';
import type { CatalogueSection } from '@/components/permissions/permission-matrix';
import { GroupForm } from './group-form';

export default function CreateGroup({ catalogue }: { catalogue: CatalogueSection[] }) {
    return (
        <AppLayout title="New group" breadcrumbs={[{ label: 'Settings', href: '/app/settings' }, { label: 'Groups', href: '/app/groups' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New group</h1>
            <GroupForm catalogue={catalogue} submitUrl="/app/groups" method="post" />
        </AppLayout>
    );
}
