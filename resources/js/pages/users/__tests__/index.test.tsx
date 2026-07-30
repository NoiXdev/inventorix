import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { delete: vi.fn(), get: vi.fn(), visit: vi.fn() } }));

import UsersIndex from '../index';

describe('UsersIndex', () => {
    it('renders a user row with person name, email and assets count', () => {
        render(<UsersIndex users={{ data: [{ id: 'u1', name: 'Ada Lovelace', email: 'ada@x.de', login_enabled: true, assets_count: 2 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('ada@x.de')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('renders a dash for a user with no linked person', () => {
        render(<UsersIndex users={{ data: [{ id: 'u2', name: null, email: 'bob@x.de', login_enabled: false, assets_count: 0 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('bob@x.de')).toBeInTheDocument();
        expect(screen.getAllByText('—').length).toBeGreaterThan(0);
    });
});
