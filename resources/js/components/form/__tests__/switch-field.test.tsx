import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { SwitchField } from '../switch-field';

describe('SwitchField', () => {
    it('renders the label and reflects checked state', () => {
        render(<SwitchField id="login_enabled" label="Login enabled" checked={true} onChange={() => {}} />);
        expect(screen.getByText('Login enabled')).toBeInTheDocument();
        expect(screen.getByRole('switch')).toHaveAttribute('aria-checked', 'true');
    });

    it('fires onChange with the new boolean when toggled', () => {
        const onChange = vi.fn();
        render(<SwitchField id="login_enabled" label="Login enabled" checked={false} onChange={onChange} />);
        fireEvent.click(screen.getByRole('switch'));
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('shows the error message when present', () => {
        render(<SwitchField id="login_enabled" label="Login" checked={false} onChange={() => {}} error="bad" />);
        expect(screen.getByText('bad')).toBeInTheDocument();
    });
});
