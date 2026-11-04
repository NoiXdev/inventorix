import AppLayout from '@/layouts/app-layout';
import { CategoryForm, type CategoryInitial, type Option } from './category-form';

export default function EditCategory({ category, typeOptions }: { category: CategoryInitial; typeOptions: Option[] }) {
    return (
        <AppLayout title="Edit category" breadcrumbs={[{ label: 'Categories', href: '/app/categories' }, { label: category.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit category</h1>
            <CategoryForm initial={category} typeOptions={typeOptions} submitUrl={`/app/categories/${category.id}`} method="put" />
        </AppLayout>
    );
}
