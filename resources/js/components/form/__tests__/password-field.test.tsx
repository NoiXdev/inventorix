import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PasswordField } from '../password-field';

describe('PasswordField', () => {
    it('renders a masked input with the label', () => {
        render(<PasswordField id="password" label="Password" value="" onChange={() => {}} />);
        expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password');
    });

    it('fires onChange with the typed value', () => {
        const onChange = vi.fn();
        render(<PasswordField id="password" label="Password" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
        expect(onChange).toHaveBeenCalledWith('secret');
    });

    it('shows the error message when present', () => {
        render(<PasswordField id="password" label="Password" value="" onChange={() => {}} error="too short" />);
        expect(screen.getByText('too short')).toBeInTheDocument();
    });
});
