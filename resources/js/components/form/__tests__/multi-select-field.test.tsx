import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MultiSelectField } from '../multi-select-field';

const opts = [{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Bravo' }];

describe('MultiSelectField', () => {
    it('adds and removes values', () => {
        const onChange = vi.fn();
        const { rerender } = render(<MultiSelectField id="x" label="X" options={opts} value={[]} onChange={onChange} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(onChange).toHaveBeenCalledWith(['a']);

        rerender(<MultiSelectField id="x" label="X" options={opts} value={['a']} onChange={onChange} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(onChange).toHaveBeenCalledWith([]);
    });

    it('filters by search', () => {
        render(<MultiSelectField id="x" label="X" options={opts} value={[]} onChange={vi.fn()} />);
        fireEvent.change(screen.getByPlaceholderText('Suchen…'), { target: { value: 'brav' } });
        expect(screen.queryByText('Alpha')).not.toBeInTheDocument();
        expect(screen.getByText('Bravo')).toBeInTheDocument();
    });
});
