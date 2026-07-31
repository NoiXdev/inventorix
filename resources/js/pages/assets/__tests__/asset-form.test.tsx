import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const submit = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const data: Record<string, unknown> = { ...initial };
        return {
            data,
            setData: (k: string, v: unknown) => { data[k] = v; },
            submit: (...a: unknown[]) => submit(...a),
            processing: false,
            errors: {},
        };
    },
}));

import { AssetForm, type AssetOptions } from '../asset-form';

const options: AssetOptions = {
    stateOptions: [{ value: 'in_use', label: 'In use' }],
    buyTypeOptions: [],
    assetTypeOptions: [{ value: 't1', label: 'Laptop' }],
    ownerOptions: [{ value: 'p1', label: 'Ada Lovelace' }],
    placeOptions: [],
    modelOptions: [],
    personCreateUrl: '/app/people/quick',
};

describe('AssetForm owner field', () => {
    it('renders the Owner field as a PersonPicker with an inline create action', () => {
        render(<AssetForm options={options} submitUrl="/app/assets" method="post" />);
        expect(screen.getByText('Owner')).toBeInTheDocument();
        // The PersonPicker's in-field "+" create action is present.
        expect(screen.getByRole('button', { name: 'Neue Person anlegen' })).toBeInTheDocument();
    });

    it('opens the create dialog from the Owner field without submitting the form', () => {
        render(<AssetForm options={options} submitUrl="/app/assets" method="post" />);
        fireEvent.click(screen.getByRole('button', { name: 'Neue Person anlegen' }));
        expect(screen.getByLabelText(/vorname/i)).toBeInTheDocument();
        expect(submit).not.toHaveBeenCalled();
    });
});
