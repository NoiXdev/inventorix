import AppLayout from '@/layouts/app-layout';
import { HandoverWizard, type HandoverWizardProps } from './handover-wizard';

export default function CreateHandover(props: HandoverWizardProps) {
    return (
        <AppLayout title="New handover" breadcrumbs={[{ label: 'Handovers', href: '/app/handovers' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New handover</h1>
            <HandoverWizard {...props} />
        </AppLayout>
    );
}
