import AppLayout from '@/layouts/app-layout';
import { UserForm, type GroupOption } from './user-form';
import type { CatalogueSection } from '@/components/permissions/permission-matrix';

interface Props { groupOptions: GroupOption[]; catalogue: CatalogueSection[]; canAssign: boolean; canAssignPrivileged: boolean }

export default function CreateUser({ groupOptions, catalogue, canAssign, canAssignPrivileged }: Props) {
    return (
        <AppLayout title="New user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New user</h1>
            <p className="mb-4 text-sm text-muted-foreground">
                If login is enabled, the user receives an email to set their own password.
            </p>
            <UserForm submitUrl="/app/users" method="post" groupOptions={groupOptions} catalogue={catalogue} canAssign={canAssign} canAssignPrivileged={canAssignPrivileged} />
        </AppLayout>
    );
}
