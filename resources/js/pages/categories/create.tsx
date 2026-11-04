import AppLayout from '@/layouts/app-layout';
import { CategoryForm, type Option } from './category-form';

export default function CreateCategory({ typeOptions }: { typeOptions: Option[] }) {
    return (
        <AppLayout title="New category" breadcrumbs={[{ label: 'Categories', href: '/app/categories' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New category</h1>
            <CategoryForm typeOptions={typeOptions} submitUrl="/app/categories" method="post" />
        </AppLayout>
    );
}
