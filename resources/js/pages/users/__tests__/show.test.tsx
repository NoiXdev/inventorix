import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { visit: vi.fn(), delete: vi.fn() } }));

import UserShow from '../show';

const user = { id: 'u1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', login_enabled: true, assets_count: 1 };

describe('UserShow', () => {
    it('lists owned assets', () => {
        render(<UserShow user={user} assets={[{ id: 'a1', model_name: 'X1', serial_number: 'SN1', state: 'in-use', state_label: 'In Benutzung', place_name: 'HQ' }]} />);
        expect(screen.getByText('X1')).toBeInTheDocument();
    });
    it('shows an empty state', () => {
        render(<UserShow user={{ ...user, assets_count: 0 }} assets={[]} />);
        expect(screen.getByText(/owns no assets/i)).toBeInTheDocument();
    });
    it('shows a login enabled badge', () => {
        render(<UserShow user={user} assets={[]} />);
        expect(screen.getByText('Login enabled')).toBeInTheDocument();
    });
    it('shows a no-login badge when login is disabled', () => {
        render(<UserShow user={{ ...user, login_enabled: false }} assets={[]} />);
        expect(screen.getByText('No login')).toBeInTheDocument();
    });
});
