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

const personOptions = [{ value: 'p1', label: 'Ada Lovelace' }];

describe('UserForm', () => {
    it('renders person, email and login toggle fields with no name fields', () => {
        render(<UserForm personOptions={personOptions} personCreateUrl="/app/people/quick" submitUrl="/app/users" method="post" />);
        expect(screen.getByText('Person')).toBeInTheDocument();
        expect(screen.getByLabelText('Login email')).toBeInTheDocument();
        expect(screen.getByLabelText('Login enabled')).toBeInTheDocument();
        expect(screen.queryByLabelText(/first name/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/last name/i)).not.toBeInTheDocument();
    });

    it('submits person_id, email and login_enabled on save', () => {
        render(<UserForm
            initial={{ id: 'u1', email: 'ada@x.de', login_enabled: true, person_id: 'p1' }}
            personOptions={personOptions}
            personCreateUrl="/app/people/quick"
            submitUrl="/app/users/u1"
            method="put"
        />);
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(submit).toHaveBeenCalledWith('put', '/app/users/u1');
    });

    it('shows a self-guard description when isSelf is true', () => {
        render(<UserForm
            initial={{ id: 'u1', email: 'ada@x.de', login_enabled: true, person_id: 'p1' }}
            personOptions={personOptions}
            personCreateUrl="/app/people/quick"
            submitUrl="/app/users/u1"
            method="put"
            isSelf
        />);
        expect(screen.getByText('You cannot disable your own login.')).toBeInTheDocument();
    });
});
