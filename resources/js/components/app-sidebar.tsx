import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { navGroups } from '@/config/nav';

export function AppSidebar() {
    const path = usePage().url.split('?')[0];
    return (
        <aside className="hidden w-60 shrink-0 border-r bg-card md:flex md:flex-col">
            <div className="flex h-14 items-center border-b px-4 font-semibold">Inventorix</div>
            <nav className="flex-1 space-y-6 overflow-y-auto p-3">
                {navGroups.map((group) => (
                    <div key={group.label}>
                        <div className="px-2 pb-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {group.label}
                        </div>
                        <ul className="space-y-1">
                            {group.items.map((item) => {
                                const active = item.match(path);
                                const Icon = item.icon;
                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm',
                                                active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                            )}
                                        >
                                            <Icon className="h-4 w-4" /> {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </aside>
    );
}
