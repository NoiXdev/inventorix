import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { AcceptanceForm, type AcceptanceData } from '@/components/acceptance/acceptance-form';

interface Props {
    acceptance: AcceptanceData;
    submitUrl: string;
}

/** Answering a check-out acceptance from inside the app. */
export default function MyAcceptance({ acceptance, submitUrl }: Props) {
    return (
        <AppLayout title="Confirm receipt">
            <div className="mx-auto w-full max-w-2xl space-y-4">
                <Link href="/app/my-items" className="text-sm text-muted-foreground hover:underline">Back to my items</Link>
                <Card>
                    <AcceptanceForm acceptance={acceptance} submitUrl={submitUrl} />
                </Card>
            </div>
        </AppLayout>
    );
}
