import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import type { BreadcrumbItem } from '@/types';

interface Props {
    title: string;
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}

export default function AppLayout({ title, breadcrumbs, children }: Props) {
    return (
        <div className="flex h-screen overflow-hidden">
            <Head title={title} />
            <AppSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <AppTopbar breadcrumbs={breadcrumbs} />
                <main className="flex-1 overflow-y-auto p-6">{children}</main>
            </div>
            <Toaster richColors position="top-right" />
        </div>
    );
}
