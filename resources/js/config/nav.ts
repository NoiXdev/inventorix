import { LayoutDashboard, Boxes, Factory } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    match: (path: string) => boolean;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

// Only the areas that exist in Spec 1. Later specs append here.
export const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', href: '/app', icon: LayoutDashboard, match: (p) => p === '/app' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Manufacturers', href: '/app/manufacturers', icon: Factory, match: (p) => p.startsWith('/app/manufacturers') },
        ],
    },
];
