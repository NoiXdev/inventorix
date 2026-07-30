import { BarChart3, LayoutDashboard, Boxes, Contact, Factory, FileSignature, MapPin, Package, QrCode, Settings as SettingsIcon, Tag, Users } from 'lucide-react';
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
            { label: 'People', href: '/app/people', icon: Contact, match: (p) => p.startsWith('/app/people') },
            { label: 'Manufacturers', href: '/app/manufacturers', icon: Factory, match: (p) => p.startsWith('/app/manufacturers') },
            { label: 'Places', href: '/app/places', icon: MapPin, match: (p) => p.startsWith('/app/places') },
            { label: 'Asset types', href: '/app/asset-types', icon: Tag, match: (p) => p.startsWith('/app/asset-types') },
            { label: 'Asset models', href: '/app/asset-models', icon: Boxes, match: (p) => p.startsWith('/app/asset-models') },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', href: '/app/users', icon: Users, match: (p) => p.startsWith('/app/users') },
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
