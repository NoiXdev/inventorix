import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DateField } from '../date-field';

describe('DateField', () => {
    it('renders a date input with the label and value', () => {
        render(<DateField id="buy_date" label="Buy date" value="2024-01-15" onChange={() => {}} />);
        const input = screen.getByLabelText('Buy date');
        expect(input).toHaveAttribute('type', 'date');
        expect(input).toHaveValue('2024-01-15');
    });
    it('fires onChange with the new value', () => {
        const onChange = vi.fn();
        render(<DateField id="buy_date" label="Buy date" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Buy date'), { target: { value: '2025-02-20' } });
        expect(onChange).toHaveBeenCalledWith('2025-02-20');
    });
    it('shows the error', () => {
        render(<DateField id="buy_date" label="Buy date" value="" onChange={() => {}} error="bad date" />);
        expect(screen.getByText('bad date')).toBeInTheDocument();
    });
});
