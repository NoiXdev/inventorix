import AppLayout from '@/layouts/app-layout';
import { DepreciationForm } from './depreciation-form';
import type { Depreciation, Option } from './types';

export default function EditDepreciation({ depreciation, typeOptions }: { depreciation: Depreciation; typeOptions: Option[] }) {
    return (
        <AppLayout title="Edit depreciation" breadcrumbs={[{ label: 'Depreciations', href: '/app/depreciations' }, { label: depreciation.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit depreciation</h1>
            <DepreciationForm initial={depreciation} typeOptions={typeOptions} submitUrl={`/app/depreciations/${depreciation.id}`} method="put" />
        </AppLayout>
    );
}
