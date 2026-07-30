import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { UserForm } from './user-form';

interface Props { user: { id: string; email: string | null; login_enabled: boolean; person_id: string | null }; personOptions: { value: string; label: string }[]; isSelf: boolean }

export default function EditUser({ user, personOptions, isSelf }: Props) {
    return (
        <AppLayout title="Edit user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: user.email ?? 'User' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Edit user</h1>
                {user.email && (
                    <Button type="button" variant="outline"
                        onClick={() => router.post(`/app/users/${user.id}/send-reset`)}>
                        Send password reset email
                    </Button>
                )}
            </div>
            <UserForm initial={user} personOptions={personOptions} submitUrl={`/app/users/${user.id}`} method="put" isSelf={isSelf} />
        </AppLayout>
    );
}
