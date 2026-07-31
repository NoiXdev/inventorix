import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ComboboxField } from '../combobox-field';

const options = [
    { value: 'a', label: 'Ada Lovelace' },
    { value: 'g', label: 'Grace Hopper' },
];

function open() {
    fireEvent.click(screen.getByRole('button', { name: /select person/i }));
}

describe('ComboboxField', () => {
    it('shows the selected option label on the trigger', () => {
        render(<ComboboxField id="p" label="Person" value="g" onChange={() => {}} options={options} />);
        expect(screen.getByRole('button')).toHaveTextContent('Grace Hopper');
    });

    it('filters options by the search query', () => {
        render(<ComboboxField id="p" label="Person" value="" onChange={() => {}} options={options} placeholder="Select person…" />);
        open();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'grace' } });
        expect(screen.getByText('Grace Hopper')).toBeInTheDocument();
        expect(screen.queryByText('Ada Lovelace')).not.toBeInTheDocument();
    });

    it('calls onChange with the option value and closes on select', () => {
        const onChange = vi.fn();
        render(<ComboboxField id="p" label="Person" value="" onChange={onChange} options={options} placeholder="Select person…" />);
        open();
        fireEvent.click(screen.getByText('Ada Lovelace'));
        expect(onChange).toHaveBeenCalledWith('a');
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });

    it('offers a nullable clear option that calls onChange with empty string', () => {
        const onChange = vi.fn();
        render(<ComboboxField id="p" label="Person" value="a" onChange={onChange} options={options} nullable placeholder="Select person…" />);
        open();
        fireEvent.click(screen.getByText('—'));
        expect(onChange).toHaveBeenCalledWith('');
    });

    it('closes on Escape', () => {
        render(<ComboboxField id="p" label="Person" value="" onChange={() => {}} options={options} placeholder="Select person…" />);
        open();
        expect(screen.getByRole('textbox')).toBeInTheDocument();
        fireEvent.keyDown(screen.getByRole('textbox'), { key: 'Escape' });
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });

    it('renders the footer slot with the current query', () => {
        render(
            <ComboboxField id="p" label="Person" value="" onChange={() => {}} options={options}
                placeholder="Select person…"
                footer={(q) => <button type="button">create {q}</button>} />,
        );
        open();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Zoe' } });
        expect(screen.getByRole('button', { name: 'create Zoe' })).toBeInTheDocument();
    });
});
