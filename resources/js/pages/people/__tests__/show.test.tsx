import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { visit: vi.fn(), delete: vi.fn() } }));

import PersonShow from '../show';

const person = { id: 'p1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', assets_count: 1 };

describe('PersonShow', () => {
    it('lists owned assets', () => {
        render(<PersonShow person={person} assets={[{ id: 'a1', model_name: 'X1', serial_number: 'SN1', state: 'in-use', state_label: 'In Benutzung', place_name: 'HQ' }]} />);
        expect(screen.getByText('X1')).toBeInTheDocument();
    });
    it('shows an empty state', () => {
        render(<PersonShow person={{ ...person, assets_count: 0 }} assets={[]} />);
        expect(screen.getByText(/owns no assets/i)).toBeInTheDocument();
    });
});
