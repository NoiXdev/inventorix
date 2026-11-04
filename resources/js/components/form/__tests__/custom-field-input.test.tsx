import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CustomFieldInput, type CustomFieldDefinition } from '../custom-field-input';

const field = (overrides: Partial<CustomFieldDefinition>): CustomFieldDefinition => ({
    db_column: 'cf_x', name: 'X', element: 'text', format: 'ANY', help_text: null, required: false, options: [], ...overrides,
});

describe('CustomFieldInput', () => {
    it('renders a text input with help text and a required marker', () => {
        const onChange = vi.fn();
        render(<CustomFieldInput field={field({ name: 'MAC', required: true, help_text: 'Colon-separated' })} value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText(/MAC/), { target: { value: '00:11' } });
        expect(onChange).toHaveBeenCalledWith('00:11');
        expect(screen.getByText('Colon-separated')).toBeInTheDocument();
        expect(screen.getByText('*')).toBeInTheDocument();
    });

    it('toggles checkbox choices as an array', () => {
        const onChange = vi.fn();
        render(<CustomFieldInput field={field({ element: 'checkbox', options: ['USB', 'LAN'] })} value={['USB']} onChange={onChange} />);
        const boxes = screen.getAllByRole('checkbox');
        fireEvent.click(boxes[1]);
        expect(onChange).toHaveBeenCalledWith(['USB', 'LAN']);
        fireEvent.click(boxes[0]);
        expect(onChange).toHaveBeenCalledWith([]);
    });

    it('picks a radio option', () => {
        const onChange = vi.fn();
        render(<CustomFieldInput field={field({ element: 'radio', options: ['Yes', 'No'] })} value={null} onChange={onChange} />);
        fireEvent.click(screen.getByLabelText('No'));
        expect(onChange).toHaveBeenCalledWith('No');
    });

    it('converts datetimes between the server and the datetime-local input', () => {
        const onChange = vi.fn();
        render(<CustomFieldInput field={field({ name: 'At', element: 'datetime_picker' })} value="2027-03-01 09:30:00" onChange={onChange} />);
        const input = screen.getByLabelText('At') as HTMLInputElement;
        expect(input.value).toBe('2027-03-01T09:30');
        fireEvent.change(input, { target: { value: '2027-03-02T10:15' } });
        expect(onChange).toHaveBeenCalledWith('2027-03-02 10:15:00');
    });
});
