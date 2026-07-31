import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const post = vi.fn();
vi.mock('axios', () => ({
    default: {
        post: (...a: unknown[]) => post(...a),
        isAxiosError: (e: unknown): boolean => !!e && typeof e === 'object' && 'response' in e,
    },
}));

import { PersonPicker } from '../person-picker';

const options = [{ value: 'a', label: 'Ada Lovelace' }];

function openPanel() {
    fireEvent.click(screen.getByRole('button', { name: /select person/i }));
}

describe('PersonPicker', () => {
    beforeEach(() => { post.mockReset(); });

    it('shows the create action only when the query has no exact match', () => {
        render(<PersonPicker id="person_id" label="Person" value="" onChange={() => {}} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Ada Lovelace' } });
        expect(screen.queryByRole('button', { name: /als person anlegen/i })).not.toBeInTheDocument();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        expect(screen.getByRole('button', { name: /als person anlegen/i })).toBeInTheDocument();
    });

    it('opens the dialog with the name prefilled from the query', () => {
        render(<PersonPicker id="person_id" label="Person" value="" onChange={() => {}} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));
        expect(screen.getByLabelText(/vorname/i)).toHaveValue('Max');
        expect(screen.getByLabelText(/nachname/i)).toHaveValue('Muster');
    });

    it('posts the new person and selects the returned id', async () => {
        post.mockResolvedValue({ data: { id: 'new-1', name: 'Max Muster' } });
        const onChange = vi.fn();
        render(<PersonPicker id="person_id" label="Person" value="" onChange={onChange} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));
        fireEvent.click(screen.getByRole('button', { name: /^anlegen$/i }));

        await waitFor(() => expect(onChange).toHaveBeenCalledWith('new-1'));
        expect(post).toHaveBeenCalledWith('/app/people/quick',
            { firstname: 'Max', lastname: 'Muster', email: '' },
            expect.objectContaining({ headers: { Accept: 'application/json' } }));
    });

    it('maps 422 validation errors onto the dialog fields', async () => {
        post.mockRejectedValue({ response: { status: 422, data: { errors: { lastname: ['Nachname fehlt.'] } } } });
        render(<PersonPicker id="person_id" label="Person" value="" onChange={() => {}} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));
        fireEvent.click(screen.getByRole('button', { name: /^anlegen$/i }));
        await waitFor(() => expect(screen.getByText('Nachname fehlt.')).toBeInTheDocument());
    });

    it('shows a generic error message on a non-422 failure and does not select anything', async () => {
        post.mockRejectedValue({ response: { status: 500 } });
        const onChange = vi.fn();
        render(<PersonPicker id="person_id" label="Person" value="" onChange={onChange} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));
        fireEvent.click(screen.getByRole('button', { name: /^anlegen$/i }));

        await waitFor(() =>
            expect(screen.getByText('Person konnte nicht angelegt werden. Bitte erneut versuchen.')).toBeInTheDocument(),
        );
        expect(onChange).not.toHaveBeenCalled();
    });

    it('clears the stale generic error banner when the create dialog is reopened', async () => {
        post.mockRejectedValue({ response: { status: 500 } });
        render(<PersonPicker id="person_id" label="Person" value="" onChange={() => {}} options={options} createUrl="/app/people/quick" />);
        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));
        fireEvent.click(screen.getByRole('button', { name: /^anlegen$/i }));

        await waitFor(() =>
            expect(screen.getByText('Person konnte nicht angelegt werden. Bitte erneut versuchen.')).toBeInTheDocument(),
        );

        fireEvent.click(screen.getByRole('button', { name: /abbrechen/i }));

        openPanel();
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Max Muster' } });
        fireEvent.click(screen.getByRole('button', { name: /als person anlegen/i }));

        expect(screen.queryByText('Person konnte nicht angelegt werden. Bitte erneut versuchen.')).not.toBeInTheDocument();
    });

    it('opens an empty create dialog via the trailing "+" action without opening the panel', () => {
        render(<PersonPicker id="person_id" label="Person" value="" onChange={() => {}} options={options} createUrl="/app/people/quick" />);
        // The "+" action is always visible next to the field, independent of the dropdown.
        fireEvent.click(screen.getByRole('button', { name: 'Neue Person anlegen' }));
        expect(screen.getByLabelText(/vorname/i)).toHaveValue('');
        expect(screen.getByLabelText(/nachname/i)).toHaveValue('');
        expect(screen.getByLabelText(/e-mail/i)).toHaveValue('');
    });
});
