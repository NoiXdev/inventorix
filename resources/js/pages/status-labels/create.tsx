import AppLayout from '@/layouts/app-layout';
import { StatusLabelForm, type Option } from './status-label-form';

export default function CreateStatusLabel({ typeOptions }: { typeOptions: Option[] }) {
    return (
        <AppLayout title="New status label" breadcrumbs={[{ label: 'Status labels', href: '/app/status-labels' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New status label</h1>
            <StatusLabelForm typeOptions={typeOptions} submitUrl="/app/status-labels" method="post" />
        </AppLayout>
    );
}
