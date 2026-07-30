import { Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav className="flex items-center gap-1 text-sm text-muted-foreground">
            {items.map((item, i) => (
                <span key={i} className="flex items-center gap-1">
                    {i > 0 && <span>/</span>}
                    {item.href ? (
                        <Link href={item.href} className="hover:text-foreground">{item.label}</Link>
                    ) : (
                        <span className="text-foreground">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
