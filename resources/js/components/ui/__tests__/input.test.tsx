import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Input } from '../input';

describe('Input autofill suppression', () => {
    it('suppresses autofill and password managers by default', () => {
        render(<Input aria-label="f" />);
        const input = screen.getByLabelText('f');
        expect(input).toHaveAttribute('autocomplete', 'off');
        expect(input).toHaveAttribute('data-1p-ignore', '');
        expect(input).toHaveAttribute('data-lpignore', 'true');
        expect(input).toHaveAttribute('data-form-type', 'other');
        expect(input).toHaveAttribute('data-bwignore', '');
    });

    it('honors an explicit autoComplete and drops the ignore hints', () => {
        render(<Input aria-label="f" autoComplete="username" />);
        const input = screen.getByLabelText('f');
        expect(input).toHaveAttribute('autocomplete', 'username');
        expect(input).not.toHaveAttribute('data-1p-ignore');
        expect(input).not.toHaveAttribute('data-lpignore');
        expect(input).not.toHaveAttribute('data-form-type');
        expect(input).not.toHaveAttribute('data-bwignore');
    });
});
