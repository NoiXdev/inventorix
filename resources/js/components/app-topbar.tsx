import { Input } from '@/components/ui/input';
import { ThemeToggle } from '@/components/theme-toggle';
import { UserMenu } from '@/components/user-menu';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

export function AppTopbar({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <header className="flex h-14 items-center gap-4 border-b bg-card px-4">
            <div className="flex-1"><Breadcrumbs items={breadcrumbs} /></div>
            <Input placeholder="Search…" className="hidden w-64 lg:block" disabled />
            <ThemeToggle />
            <UserMenu />
        </header>
    );
}
