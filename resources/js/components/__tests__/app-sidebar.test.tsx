import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ children, href, className }: any) => <a href={href} className={className}>{children}</a>,
}));

import { AppSidebar } from '../app-sidebar';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can }, statusNav: [] }; };

describe('AppSidebar', () => {
    it('hides entries the user may not use', () => {
        setCan({ 'assets.view': true });
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: /Assets/ })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Users/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Settings/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Groups/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Handovers/ })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Dashboard/ })).toBeInTheDocument();
    });

    it('shows Users and Settings with users.view and superuser', () => {
        setCan({ 'users.view': true, superuser: true });
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: /Users/ })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Settings/ })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Groups/ })).toHaveAttribute('href', '/app/groups');
    });

    it('shows Groups to superusers only, not to admins', () => {
        setCan({ admin: true, 'users.view': true });
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: /Users/ })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Groups/ })).not.toBeInTheDocument();
    });

    it('drops a group heading when all of its items are hidden', () => {
        setCan({ 'assets.view': true });
        render(<AppSidebar />);
        expect(screen.getByText('Inventory')).toBeInTheDocument();
        expect(screen.queryByText('Administration')).not.toBeInTheDocument();
        expect(screen.queryByText('Reports')).not.toBeInTheDocument();
        expect(screen.queryByText('Tools')).not.toBeInTheDocument();
    });

    it('gates the QR generator on assets.create', () => {
        setCan({ 'assets.view': true });
        const { unmount } = render(<AppSidebar />);
        expect(screen.queryByRole('link', { name: /QR Generator/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'assets.create': true });
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: /QR Generator/ })).toBeInTheDocument();
    });
});
