import AppLayout from '@/layouts/app-layout';
import { StatusLabelForm, type Option, type StatusLabelInitial } from './status-label-form';

export default function EditStatusLabel({ label, typeOptions }: { label: StatusLabelInitial; typeOptions: Option[] }) {
    return (
        <AppLayout title="Edit status label" breadcrumbs={[{ label: 'Status labels', href: '/app/status-labels' }, { label: label.name ?? 'Status label' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit status label</h1>
            <StatusLabelForm initial={label} typeOptions={typeOptions} submitUrl={`/app/status-labels/${label.id}`} method="put" />
        </AppLayout>
    );
}
