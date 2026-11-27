import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ children, href }: any) => <a href={href}>{children}</a>,
    router: { delete: vi.fn(), get: vi.fn(), visit: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import GroupsIndex from '../index';

const groups = { data: [{ id: 'g1', name: 'IT', users_count: 1, summary: 'x' }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never };

describe('GroupsIndex', () => {
    it('shows the delete error returned under the group key', () => {
        page.props = { errors: { group: 'The last superuser group cannot be deleted.' } };
        render(<GroupsIndex groups={groups} />);
        expect(screen.getByRole('alert')).toHaveTextContent('The last superuser group cannot be deleted.');
    });

    it('shows no alert without errors', () => {
        page.props = { errors: {} };
        render(<GroupsIndex groups={groups} />);
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});
