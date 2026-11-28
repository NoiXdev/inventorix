import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const submit = vi.fn();
const initials: Record<string, unknown>[] = [];
vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        initials.push(initial);
        const data: Record<string, unknown> = { ...initial };
        return {
            data,
            setData: (k: string, v: unknown) => { data[k] = v; },
            submit: (...a: unknown[]) => submit(...a),
            processing: false,
            errors: {},
        };
    },
}));

import { UserForm } from '../user-form';

describe('UserForm', () => {
    it('renders firstname, lastname, email and login toggle fields with no person picker', () => {
        render(<UserForm submitUrl="/app/users" method="post" />);
        expect(screen.getByLabelText(/first name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/last name/i)).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Login enabled')).toBeInTheDocument();
        expect(screen.queryByText('Person')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /select person/i })).not.toBeInTheDocument();
    });

    it('submits firstname, lastname, email and login_enabled on save', () => {
        render(<UserForm
            initial={{ id: 'u1', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', login_enabled: true }}
            submitUrl="/app/users/u1"
            method="put"
        />);
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(submit).toHaveBeenCalledWith('put', '/app/users/u1');
    });

    it('shows a self-guard description when isSelf is true', () => {
        render(<UserForm
            initial={{ id: 'u1', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', login_enabled: true }}
            submitUrl="/app/users/u1"
            method="put"
            isSelf
        />);
        expect(screen.getByText('You cannot disable your own login.')).toBeInTheDocument();
    });

    it('shows a generic login description when isSelf is not set', () => {
        render(<UserForm submitUrl="/app/users" method="post" />);
        expect(screen.getByText('Without login, this user is only an asset owner.')).toBeInTheDocument();
    });
});

describe('UserForm groups and permissions', () => {
    const catalogue = [
        { section: 'system', label: 'System', permissions: [{ value: 'superuser', label: 'Superuser' }, { value: 'admin', label: 'Admin' }] },
        { section: 'assets', label: 'Assets', permissions: [{ value: 'assets.view', label: 'View' }] },
    ];
    const groupOptions = [{ value: 'g1', label: 'IT-Admin', privileged: false }, { value: 'g2', label: 'Root', privileged: true }];
    const base = {
        initial: { id: 'u1', firstname: 'Ada', lastname: 'L', email: 'a@x.de', login_enabled: true, groups: ['g1'], permissions: { 'assets.view': 1 as const } },
        submitUrl: '/app/users/u1', method: 'put' as const, catalogue, groupOptions,
        effective: { 'assets.view': { granted: true, via: 'IT-Admin' }, superuser: { granted: false, via: null }, admin: { granted: false, via: null } },
    };

    it('shows neither panel nor sends the fields without canAssign', () => {
        initials.length = 0;
        render(<UserForm {...base} canAssign={false} canAssignPrivileged={false} />);
        expect(screen.queryByText('Groups')).not.toBeInTheDocument();
        expect(screen.queryByText('Permissions')).not.toBeInTheDocument();
        expect(initials[0]).not.toHaveProperty('groups');
        expect(initials[0]).not.toHaveProperty('permissions');
    });

    it('shows the groups select and the tristate matrix with effective results when canAssign', () => {
        initials.length = 0;
        render(<UserForm {...base} canAssign canAssignPrivileged={false} />);
        expect(screen.getByText('Groups')).toBeInTheDocument();
        expect(screen.getByLabelText('Member of')).toBeInTheDocument();
        expect(screen.getByText('IT-Admin')).toBeInTheDocument();
        expect(screen.getByText('Permissions')).toBeInTheDocument();
        expect(screen.getByRole('radiogroup', { name: 'Assets View' })).toBeInTheDocument();
        expect(screen.getByText('Granted by IT-Admin')).toBeInTheDocument();
        expect(initials[0]).toMatchObject({ groups: ['g1'], permissions: { 'assets.view': 1 } });
    });

    it('hides the superuser/admin rows and privileged groups unless canAssignPrivileged', () => {
        const { unmount } = render(<UserForm {...base} canAssign canAssignPrivileged={false} />);
        expect(screen.queryByRole('radiogroup', { name: 'System Superuser' })).not.toBeInTheDocument();
        expect(screen.queryByRole('radiogroup', { name: 'System Admin' })).not.toBeInTheDocument();
        expect(screen.queryByText('Root')).not.toBeInTheDocument();
        unmount();
        render(<UserForm {...base} canAssign canAssignPrivileged />);
        expect(screen.getByRole('radiogroup', { name: 'System Superuser' })).toBeInTheDocument();
        expect(screen.getByRole('radiogroup', { name: 'System Admin' })).toBeInTheDocument();
        expect(screen.getByText('Root')).toBeInTheDocument();
    });

    it('defaults to empty groups and an empty permissions object on create', () => {
        initials.length = 0;
        render(<UserForm submitUrl="/app/users" method="post" catalogue={catalogue} groupOptions={groupOptions} canAssign canAssignPrivileged />);
        expect(initials[0]).toMatchObject({ groups: [], permissions: {} });
    });

    it('renders the profile fields', () => {
        render(<UserForm submitUrl="/app/users" method="post" departmentOptions={[{ value: 'd1', label: 'Finance' }]} />);
        for (const label of ['Employee number', 'Job title', 'Department', 'Manager', 'Location', 'Phone', 'Mobile', 'Start date', 'End date', 'Notes']) {
            expect(screen.getByLabelText(label)).toBeInTheDocument();
        }
    });
});
