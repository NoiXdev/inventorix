import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({ usePage: () => page, Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { visit: vi.fn(), delete: vi.fn() } }));

import UserShow from '../show';

const user = { id: 'u1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', login_enabled: true, assets_count: 1 };

describe('UserShow', () => {
    it('lists owned assets', () => {
        render(<UserShow groups={[]} effective={{}} user={user} assets={[{ id: 'a1', name: null, asset_tag: null, model_name: 'X1', serial: 'SN1', status: { id: 's1', name: 'In Benutzung', type: 'deployable' }, location_name: 'HQ' }]} />);
        expect(screen.getByText('X1')).toBeInTheDocument();
    });
    it('shows an empty state', () => {
        render(<UserShow groups={[]} effective={{}} user={{ ...user, assets_count: 0 }} assets={[]} />);
        expect(screen.getByText(/no assets are assigned/i)).toBeInTheDocument();
    });
    it('shows a login enabled badge', () => {
        render(<UserShow groups={[]} effective={{}} user={user} assets={[]} />);
        expect(screen.getByText('Login enabled')).toBeInTheDocument();
    });
    it('shows a no-login badge when login is disabled', () => {
        render(<UserShow groups={[]} effective={{}} user={{ ...user, login_enabled: false }} assets={[]} />);
        expect(screen.getByText('No login')).toBeInTheDocument();
    });

    it('lists groups and the effective permissions, grouped by section', () => {
        page.props = { auth: { user: null, can: {} } };
        render(<UserShow user={user} assets={[]} groups={['IT-Admin', 'Support']} effective={{ 'assets.view': true, 'assets.edit': false, superuser: false, 'incidents.view': true }} />);
        expect(screen.getByText('IT-Admin')).toBeInTheDocument();
        expect(screen.getByText('Support')).toBeInTheDocument();
        expect(screen.getByText('Effective permissions')).toBeInTheDocument();
        expect(screen.getByText('assets.view')).toBeInTheDocument();
        expect(screen.getByText('incidents.view')).toBeInTheDocument();
        expect(screen.queryByText('assets.edit')).not.toBeInTheDocument();
    });

    it('shows a hint when the user has no groups or permissions', () => {
        page.props = { auth: { user: null, can: {} } };
        render(<UserShow user={user} assets={[]} groups={[]} effective={{}} />);
        expect(screen.getByText(/not in any group/i)).toBeInTheDocument();
        expect(screen.getByText(/no permissions/i)).toBeInTheDocument();
    });

    it('hides Edit and Delete without users.edit / users.delete', () => {
        page.props = { auth: { user: null, can: {} } };
        const { unmount } = render(<UserShow user={user} assets={[]} groups={[]} effective={{}} />);
        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
        unmount();
        page.props = { auth: { user: null, can: { 'users.edit': true, 'users.delete': true } } };
        render(<UserShow user={user} assets={[]} groups={[]} effective={{}} canManage />);
        expect(screen.getByRole('link', { name: 'Edit' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
    });

    it('hides Edit and Delete when this user may not be managed (more permissions than the viewer)', () => {
        page.props = { auth: { user: null, can: { 'users.edit': true, 'users.delete': true } } };
        render(<UserShow user={user} assets={[]} groups={[]} effective={{}} canManage={false} />);
        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
    });

    it('shows the profile details with a manager link', () => {
        page.props = { auth: { user: null, can: {} } };
        render(<UserShow user={{ ...user, employee_num: 'E-100', jobtitle: 'Engineer', department_id: 'd1', department_name: 'Finance', manager: { id: 'm1', name: 'Boss Person' }, location_name: 'Berlin', phone: '123', start_date: '2024-01-15' }} assets={[]} groups={[]} effective={{}} />);
        expect(screen.getByText('E-100')).toBeInTheDocument();
        expect(screen.getByText('Engineer')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Finance' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Boss Person' })).toHaveAttribute('href', '/app/users/m1');
        expect(screen.getByText('Berlin')).toBeInTheDocument();
        expect(screen.getByText('2024-01-15')).toBeInTheDocument();
    });
});
