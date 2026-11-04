import AppLayout from '@/layouts/app-layout';
import { DepreciationForm } from './depreciation-form';
import type { Option } from './types';

export default function CreateDepreciation({ typeOptions }: { typeOptions: Option[] }) {
    return (
        <AppLayout title="New depreciation" breadcrumbs={[{ label: 'Depreciations', href: '/app/depreciations' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New depreciation</h1>
            <DepreciationForm typeOptions={typeOptions} submitUrl="/app/depreciations" method="post" />
        </AppLayout>
    );
}
