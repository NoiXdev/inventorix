import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn(), visit: vi.fn(), delete: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import Accessories from '../accessories/index';
import Manufacturers from '../manufacturers/index';
import Suppliers from '../suppliers/index';
import Locations from '../locations/index';
import Categories from '../categories/index';
import AssetModels from '../asset-models/index';
import Depreciations from '../depreciations/index';
import StatusLabels from '../status-labels/index';
import CustomFields from '../custom-fields/index';
import CustomFieldsets from '../custom-fieldsets/index';
import Users from '../users/index';
import Handovers from '../handovers/index';

const meta = { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never;
const list = (r: Record<string, unknown>) => ({ data: [{ id: 'r1', name: 'Thing', ...r }], meta });

type Case = { name: string; section: string; slug: string; newLabel: string; render: () => void };
const cases: Case[] = [
    { name: 'accessories', section: 'accessories', slug: 'accessories', newLabel: 'New accessory', render: () => render(<Accessories {...(({ accessories: list({}), categoryOptions: [], manufacturerOptions: [], locationOptions: [], supplierOptions: [] }) as unknown as React.ComponentProps<typeof Accessories>)} />) },
    { name: 'manufacturers', section: 'manufacturers', slug: 'manufacturers', newLabel: 'New manufacturer', render: () => render(<Manufacturers manufacturers={list({}) as never} />) },
    { name: 'suppliers', section: 'suppliers', slug: 'suppliers', newLabel: 'New supplier', render: () => render(<Suppliers suppliers={list({}) as never} countryOptions={[]} />) },
    { name: 'locations', section: 'locations', slug: 'locations', newLabel: 'New location', render: () => render(<Locations locations={list({}) as never} countryOptions={[]} parentOptions={[]} />) },
    { name: 'categories', section: 'categories', slug: 'categories', newLabel: 'New category', render: () => render(<Categories categories={list({}) as never} typeOptions={[]} />) },
    { name: 'asset models', section: 'models', slug: 'asset-models', newLabel: 'New asset model', render: () => render(<AssetModels assetModels={list({}) as never} manufacturerOptions={[]} categoryOptions={[]} />) },
    { name: 'depreciations', section: 'depreciations', slug: 'depreciations', newLabel: 'New depreciation', render: () => render(<Depreciations depreciations={list({}) as never} />) },
    { name: 'status labels', section: 'statuslabels', slug: 'status-labels', newLabel: 'New status label', render: () => render(<StatusLabels labels={list({ system: false }) as never} />) },
    { name: 'custom fields', section: 'customfields', slug: 'custom-fields', newLabel: 'New custom field', render: () => render(<CustomFields customFields={list({}) as never} elementOptions={[]} />) },
    { name: 'custom fieldsets', section: 'customfields', slug: 'custom-fieldsets', newLabel: 'New fieldset', render: () => render(<CustomFieldsets customFieldsets={list({}) as never} />) },
    { name: 'users', section: 'users', slug: 'users', newLabel: 'New user', render: () => render(<Users users={list({ email: 'a@x.de', login_enabled: true, assets_count: 0 }) as never} />) },
];

describe.each(cases)('$name index permissions', (c) => {
    beforeEach(() => { page.props = { auth: { user: null, can: {} } }; });

    it('hides New, Edit and Delete without permissions', () => {
        c.render();
        expect(screen.queryByRole('link', { name: c.newLabel })).not.toBeInTheDocument();
        expect(document.querySelector(`a[href="/app/${c.slug}/r1/edit"]`)).toBeNull();
        expect(document.querySelector('svg.lucide-trash-2')).toBeNull();
    });

    it('shows New, Edit and Delete with permissions', () => {
        page.props = { auth: { user: null, can: { [`${c.section}.create`]: true, [`${c.section}.edit`]: true, [`${c.section}.delete`]: true } } };
        c.render();
        expect(screen.getByRole('link', { name: c.newLabel })).toBeInTheDocument();
        expect(document.querySelector(`a[href="/app/${c.slug}/r1/edit"]`)).not.toBeNull();
        expect(document.querySelector('svg.lucide-trash-2')).not.toBeNull();
    });
});

describe('handovers index permissions', () => {
    const data = { handovers: { data: [], meta } as never };
    it('hides New handover without handovers.create', () => {
        page.props = { auth: { user: null, can: {} } };
        render(<Handovers {...data} />);
        expect(screen.queryByRole('link', { name: 'New handover' })).not.toBeInTheDocument();
    });
    it('shows New handover with handovers.create', () => {
        page.props = { auth: { user: null, can: { 'handovers.create': true } } };
        render(<Handovers {...data} />);
        expect(screen.getByRole('link', { name: 'New handover' })).toBeInTheDocument();
    });
});
