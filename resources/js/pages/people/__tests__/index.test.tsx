import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { delete: vi.fn(), get: vi.fn(), visit: vi.fn() } }));

import PeopleIndex from '../index';

describe('PeopleIndex', () => {
    it('renders a person row with asset count', () => {
        render(<PeopleIndex people={{ data: [{ id: 'p1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', assets_count: 3 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });
});
