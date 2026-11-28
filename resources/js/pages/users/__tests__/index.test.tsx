import { fireEvent, render, screen } from '@testing-library/react';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const h = vi.hoisted(() => ({ can: {} as Record<string, boolean>, post: vi.fn() }));

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: null, can: h.can } } }), Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { delete: vi.fn(), get: vi.fn(), visit: vi.fn(), post: h.post } }));

import UsersIndex from '../index';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

describe('UsersIndex', () => {
    beforeEach(() => {
        h.can = { 'users.edit': true, 'users.delete': true, 'users.create': true };
        h.post.mockClear();
    });

    it('renders a user row with name, email and assets count', () => {
        render(<UsersIndex users={{ data: [{ id: 'u1', name: 'Ada Lovelace', email: 'ada@x.de', login_enabled: true, assets_count: 2 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('ada@x.de')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('renders a dash for a user with no name', () => {
        render(<UsersIndex users={{ data: [{ id: 'u2', name: null, email: 'bob@x.de', login_enabled: false, assets_count: 0 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('bob@x.de')).toBeInTheDocument();
        expect(screen.getAllByText('—').length).toBeGreaterThan(0);
    });

    // Ported from the former people/__tests__/index.test.tsx: a user with assets shows the
    // asset count badge on the row (the people-index equivalent of PeopleIndex's assertion).
    it('shows the owned-assets count for a row', () => {
        render(<UsersIndex users={{ data: [{ id: 'p1', name: 'Ada Lovelace', email: 'ada@x.de', login_enabled: true, assets_count: 3 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('renders the profile columns', () => {
        render(<UsersIndex users={{ data: [{ id: 'u1', name: 'Ada', email: 'a@x.de', login_enabled: true, assets_count: 0, employee_num: 'E-7', jobtitle: 'Engineer', department_name: 'Finance', location_name: 'Berlin' }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        for (const t of ['E-7', 'Engineer', 'Finance', 'Berlin']) expect(screen.getByText(t)).toBeInTheDocument();
    });

    const oneRow = () => render(<UsersIndex users={{ data: [{ id: 'u1', name: 'Ada', email: 'a@x.de', login_enabled: false, assets_count: 0 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);

    it('posts the selected ids to enable login when the user may edit users', () => {
        oneRow();
        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Select row' })[0]);
        fireEvent.click(screen.getByRole('button', { name: /Enable login \(1\)/ }));
        expect(h.post).toHaveBeenCalledWith('/app/users/bulk-enable-login', { ids: ['u1'] }, expect.objectContaining({ preserveScroll: true }));
    });

    it('offers no bulk enable-login without users.edit', () => {
        h.can = { 'users.create': true };
        oneRow();
        expect(screen.queryAllByRole('checkbox', { name: 'Select row' })).toHaveLength(0);
        expect(screen.queryByRole('button', { name: /Enable login/ })).not.toBeInTheDocument();
    });

    it('shows an error toast when the bulk enable-login batch is rejected', () => {
        oneRow();
        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Select row' })[0]);
        fireEvent.click(screen.getByRole('button', { name: /Enable login \(1\)/ }));
        const opts = h.post.mock.calls[0][2];
        opts.onError({ ids: 'Ada Admin cannot be managed.' });
        expect(toast.error).toHaveBeenCalledWith('Ada Admin cannot be managed.');
    });

    it('has no selection column without users.edit', () => {
        h.can = {};
        oneRow();
        expect(screen.queryAllByRole('checkbox', { name: 'Select row' })).toHaveLength(0);
    });
});
