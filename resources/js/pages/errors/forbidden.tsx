import { Link } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';

export default function Forbidden() {
    return (
        <AppLayout title="Not allowed" breadcrumbs={[{ label: 'Not allowed' }]}>
            <div className="mx-auto flex max-w-md flex-col items-center gap-4 py-16 text-center">
                <ShieldAlert className="h-12 w-12 text-muted-foreground" />
                <h1 className="text-2xl font-semibold">You don't have access to this page</h1>
                <p className="text-sm text-muted-foreground">
                    Your account is missing the permission this page needs. Ask an administrator if you think that is a mistake.
                </p>
                <Button asChild><Link href="/app">Back to the dashboard</Link></Button>
            </div>
        </AppLayout>
    );
}
