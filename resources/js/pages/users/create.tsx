import AppLayout from '@/layouts/app-layout';
import { UserForm } from './user-form';

export default function CreateUser() {
    return (
        <AppLayout title="New user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New user</h1>
            <p className="mb-4 max-w-lg text-sm text-muted-foreground">
                If login is enabled, the user receives an email to set their own password.
            </p>
            <UserForm submitUrl="/app/users" method="post" />
        </AppLayout>
    );
}
