import { BarChart3, LayoutDashboard, Boxes, LayoutList, ListPlus, Shapes, TrendingDown, Factory, FileSignature, MapPin, Package, QrCode, Settings as SettingsIcon, Tag, Users } from 'lucide-react';
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
            { label: 'Assets', href: '/app/assets', icon: Package, match: (p) => p.startsWith('/app/assets') },
            { label: 'Manufacturers', href: '/app/manufacturers', icon: Factory, match: (p) => p.startsWith('/app/manufacturers') },
            { label: 'Places', href: '/app/places', icon: MapPin, match: (p) => p.startsWith('/app/places') },
            { label: 'Asset types', href: '/app/asset-types', icon: Tag, match: (p) => p.startsWith('/app/asset-types') },
            { label: 'Categories', href: '/app/categories', icon: Shapes, match: (p) => p.startsWith('/app/categories') },
            { label: 'Asset models', href: '/app/asset-models', icon: Boxes, match: (p) => p.startsWith('/app/asset-models') },
            { label: 'Depreciations', href: '/app/depreciations', icon: TrendingDown, match: (p) => p.startsWith('/app/depreciations') },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', href: '/app/users', icon: Users, match: (p) => p.startsWith('/app/users') },
            { label: 'Custom fields', href: '/app/custom-fields', icon: ListPlus, match: (p) => p.startsWith('/app/custom-fields') },
            { label: 'Fieldsets', href: '/app/custom-fieldsets', icon: LayoutList, match: (p) => p.startsWith('/app/custom-fieldsets') },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Handovers', href: '/app/handovers', icon: FileSignature, match: (p) => p.startsWith('/app/handovers') },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Reports', href: '/app/reports', icon: BarChart3, match: (p) => p.startsWith('/app/reports') },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Settings', href: '/app/settings', icon: SettingsIcon, match: (p) => p.startsWith('/app/settings') },
        ],
    },
    {
        label: 'Tools',
        items: [
            { label: 'QR Generator', href: '/app/qr-generator', icon: QrCode, match: (p) => p.startsWith('/app/qr-generator') },
        ],
    },
];
