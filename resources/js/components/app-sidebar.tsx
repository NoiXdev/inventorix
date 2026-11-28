import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { navGroups } from '@/config/nav';
import { useCan } from '@/hooks/use-can';
import type { PageProps } from '@/types';

const linkClass = (active: boolean) => cn(
    'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm',
    active
        ? 'bg-primary text-primary-foreground'
        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
);

export function AppSidebar() {
    const { url, props } = usePage<PageProps>();
    const [path, query = ''] = url.split('?');
    const statusFilter = new URLSearchParams(query).get('filter[status_id]');
    const statusNav = props.statusNav ?? [];
    const can = useCan();
    // Same rule as DashboardController: without any permission the dashboard just redirects to My items.
    const authCan = props.auth?.can;
    const hasAnyPermission = !!authCan && !Array.isArray(authCan) && Object.values(authCan).some((v) => v === true);
    const groups = navGroups
        .map((group) => ({ ...group, items: group.items.filter((item) => (item.href !== '/app' || hasAnyPermission) && (!item.permission || [item.permission].flat().every((p) => can(p)))) }))
        .filter((group) => group.items.length > 0);

    return (
        <aside className="hidden w-60 shrink-0 border-r bg-card md:flex md:flex-col">
            <div className="flex h-14 items-center border-b px-4 font-semibold">Inventorix</div>
            <nav className="flex-1 space-y-6 overflow-y-auto p-3">
                {groups.map((group) => (
                    <div key={group.label}>
                        <div className="px-2 pb-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {group.label}
                        </div>
                        <ul className="space-y-1">
                            {group.items.map((item) => {
                                const isAssets = item.href === '/app/assets';
                                // A status entry below Assets is highlighted instead of Assets itself.
                                const active = item.match(path) && !(isAssets && path === '/app/assets' && statusNav.some((s) => s.id === statusFilter));
                                const Icon = item.icon;
                                return (
                                    <li key={item.href}>
                                        <Link href={item.href} className={linkClass(active)}>
                                            <Icon className="h-4 w-4" /> {item.label}
                                        </Link>
                                        {isAssets && statusNav.length > 0 && (
                                            <ul className="mt-1 space-y-1 pl-6">
                                                {statusNav.map((s) => (
                                                    <li key={s.id}>
                                                        <Link href={`/app/assets?filter[status_id]=${s.id}`}
                                                            className={linkClass(path === '/app/assets' && statusFilter === s.id)}>
                                                            <span className="flex-1 truncate">{s.name}</span>
                                                            <span className="text-xs tabular-nums opacity-70">{s.count}</span>
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
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
