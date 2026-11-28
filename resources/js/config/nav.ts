import { BarChart3, LayoutDashboard, Boxes, Cable, LayoutList, ListPlus, CircleDot, Shapes, TrendingDown, Factory, FileSignature, MapPin, Package, QrCode, Settings as SettingsIcon, Truck, Users, UsersRound, ClipboardCheck, KeyRound, Droplets, BadgeCheck, Building2, Cpu, PackagePlus } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    match: (path: string) => boolean;
    /** Catalogue permission(s) needed to see the entry, all of them; omitted = always visible. */
    permission?: string | string[];
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
            { label: 'Assets', href: '/app/assets', icon: Package, match: (p) => p.startsWith('/app/assets') && p !== '/app/assets/audit', permission: 'assets.view' },
            { label: 'Licenses', href: '/app/licenses', icon: KeyRound, match: (p) => p.startsWith('/app/licenses'), permission: 'licenses.view' },
            { label: 'Accessories', href: '/app/accessories', icon: Cable, match: (p) => p.startsWith('/app/accessories') , permission: 'accessories.view' },
            { label: 'Consumables', href: '/app/consumables', icon: Droplets, match: (p) => p.startsWith('/app/consumables'), permission: 'consumables.view' },
            { label: 'Components', href: '/app/components', icon: Cpu, match: (p) => p.startsWith('/app/components'), permission: 'components.view' },
            { label: 'Kits', href: '/app/kits', icon: PackagePlus, match: (p) => p.startsWith('/app/kits'), permission: 'kits.view' },
            { label: 'Manufacturers', href: '/app/manufacturers', icon: Factory, match: (p) => p.startsWith('/app/manufacturers') , permission: 'manufacturers.view' },
            { label: 'Suppliers', href: '/app/suppliers', icon: Truck, match: (p) => p.startsWith('/app/suppliers') , permission: 'suppliers.view' },
            { label: 'Locations', href: '/app/locations', icon: MapPin, match: (p) => p.startsWith('/app/locations') , permission: 'locations.view' },
            { label: 'Categories', href: '/app/categories', icon: Shapes, match: (p) => p.startsWith('/app/categories') , permission: 'categories.view' },
            { label: 'Asset models', href: '/app/asset-models', icon: Boxes, match: (p) => p.startsWith('/app/asset-models') , permission: 'models.view' },
            { label: 'Depreciations', href: '/app/depreciations', icon: TrendingDown, match: (p) => p.startsWith('/app/depreciations') , permission: 'depreciations.view' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', href: '/app/users', icon: Users, match: (p) => p.startsWith('/app/users') , permission: 'users.view' },
            { label: 'Departments', href: '/app/departments', icon: Building2, match: (p) => p.startsWith('/app/departments'), permission: 'departments.view' },
            { label: 'Groups', href: '/app/groups', icon: UsersRound, match: (p) => p.startsWith('/app/groups'), permission: 'superuser' },
            { label: 'Status labels', href: '/app/status-labels', icon: CircleDot, match: (p) => p.startsWith('/app/status-labels') , permission: 'statuslabels.view' },
            { label: 'Custom fields', href: '/app/custom-fields', icon: ListPlus, match: (p) => p.startsWith('/app/custom-fields') , permission: 'customfields.view' },
            { label: 'Fieldsets', href: '/app/custom-fieldsets', icon: LayoutList, match: (p) => p.startsWith('/app/custom-fieldsets') , permission: 'customfields.view' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Audit', href: '/app/assets/audit', icon: ClipboardCheck, match: (p) => p === '/app/assets/audit', permission: ['assets.view', 'assets.audit'] },
            { label: 'Handovers', href: '/app/handovers', icon: FileSignature, match: (p) => p.startsWith('/app/handovers') , permission: 'handovers.view' },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Acceptances', href: '/app/acceptances', icon: BadgeCheck, match: (p) => p.startsWith('/app/acceptances'), permission: 'reports.view' },
            { label: 'Reports', href: '/app/reports', icon: BarChart3, match: (p) => p.startsWith('/app/reports') , permission: 'reports.view' },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Settings', href: '/app/settings', icon: SettingsIcon, match: (p) => p.startsWith('/app/settings') , permission: 'superuser' },
        ],
    },
    {
        label: 'Tools',
        items: [
            { label: 'QR Generator', href: '/app/qr-generator', icon: QrCode, match: (p) => p.startsWith('/app/qr-generator') , permission: 'assets.create' },
        ],
    },
];
