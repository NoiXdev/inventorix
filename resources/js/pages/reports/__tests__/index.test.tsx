import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a> }));

import ReportsIndex from '../index';

describe('ReportsIndex', () => {
    it('renders a card per report linking to it', () => {
        render(<ReportsIndex reports={[
            { key: 'state_overview', label: 'Status-Übersicht', description: 'desc', icon: 'PieChart' },
        ]} />);
        const link = screen.getByRole('link', { name: /Status-Übersicht/ });
        expect(link).toHaveAttribute('href', '/app/reports/state_overview');
    });
});
