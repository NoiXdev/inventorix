import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { NumberField } from '../number-field';

describe('NumberField', () => {
    it('renders a number input with the label', () => {
        render(<NumberField id="buy_price" label="Buy price" value="199.99" onChange={() => {}} />);
        const input = screen.getByLabelText('Buy price');
        expect(input).toHaveAttribute('type', 'number');
        expect(input).toHaveValue(199.99);
    });
    it('fires onChange with the raw string value', () => {
        const onChange = vi.fn();
        render(<NumberField id="buy_price" label="Buy price" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Buy price'), { target: { value: '250' } });
        expect(onChange).toHaveBeenCalledWith('250');
    });
    it('shows the error', () => {
        render(<NumberField id="buy_price" label="Buy price" value="" onChange={() => {}} error="not a number" />);
        expect(screen.getByText('not a number')).toBeInTheDocument();
    });
});
