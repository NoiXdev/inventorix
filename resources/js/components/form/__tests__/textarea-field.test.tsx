import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TextareaField } from '../textarea-field';

describe('TextareaField', () => {
    it('renders the value and reports edits', () => {
        const onChange = vi.fn();
        render(<TextareaField id="notes" label="Notes" value="hello" onChange={onChange} />);

        const box = screen.getByLabelText('Notes');
        expect(box).toHaveValue('hello');

        fireEvent.change(box, { target: { value: 'world' } });
        expect(onChange).toHaveBeenCalledWith('world');
    });

    it('marks the required state and surfaces the error', () => {
        render(<TextareaField id="notes" label="Notes" value="" onChange={() => {}} required error="Too long" />);

        expect(screen.getByText('*')).toBeInTheDocument();
        expect(screen.getByText('Too long')).toBeInTheDocument();
        expect(screen.getByLabelText(/Notes/)).toHaveAttribute('aria-invalid', 'true');
    });
});
