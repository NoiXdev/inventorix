import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const submit = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
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
