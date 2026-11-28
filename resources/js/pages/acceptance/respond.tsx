import { Head } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { AcceptanceForm, type AcceptanceData } from '@/components/acceptance/acceptance-form';

interface Props {
    acceptance: AcceptanceData;
    /** Signed URL the answer is posted to. */
    submitUrl: string;
}

/**
 * Reached through the signed link in the check-out mail, without logging in:
 * the user accepts or declines the item, optionally with a signature.
 */
export default function AcceptanceRespond({ acceptance, submitUrl }: Props) {
    return (
        <div className="flex min-h-screen items-start justify-center bg-background p-4 sm:items-center">
            <Head title="Confirm receipt" />
            <Card className="w-full max-w-2xl">
                <AcceptanceForm acceptance={acceptance} submitUrl={submitUrl} />
            </Card>
        </div>
    );
}
