import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/departments', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn(), visit: vi.fn(), delete: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import DepartmentsIndex from '../index';

const meta = { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never;
const departments = { data: [{ id: 'd1', name: 'Finance', manager_name: null, location_name: null, users_count: 7 }], meta };

describe('Departments index', () => {
    beforeEach(() => { page.props = { auth: { user: null, can: {} } }; });

    it('links the users count to the users list filtered to the department', () => {
        page.props = { auth: { user: null, can: { 'users.view': true } } };
        render(<DepartmentsIndex departments={departments} locationOptions={[]} />);
        expect(screen.getByRole('link', { name: '7' })).toHaveAttribute('href', '/app/users?filter[department_id]=d1');
    });

    it('shows the count without a link when users cannot be viewed', () => {
        render(<DepartmentsIndex departments={departments} locationOptions={[]} />);
        expect(screen.getByText('7')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: '7' })).not.toBeInTheDocument();
    });
});
