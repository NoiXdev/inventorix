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
            post: (...a: unknown[]) => submit(...a),
            transform: () => undefined,
            processing: false,
            errors: {},
        };
    },
}));

import { AssetForm, type AssetOptions } from '../asset-form';

const options: AssetOptions = {
    statusOptions: [{ value: 's-in-use', label: 'In use' }],
    locationOptions: [],
    rtdLocationOptions: [],
    supplierOptions: [],
    modelOptions: [],
    assigneeOptions: { user: [{ value: 'p1', label: 'Ada Lovelace' }], location: [], asset: [] },
    userCreateUrl: '/app/users/quick',
};

const assignedToUser = {
    id: 'a1', name: null, asset_tag: null, status_id: 's-in-use', model_id: null, serial: null,
    assignee_type: 'user' as const, assignee_id: 'p1', location_id: null, rtd_location_id: null, supplier_id: null,
    purchase_date: null, purchase_cost: '', order_number: null, warranty_months: null,
    asset_eol_date: null, eol_explicit: false, depreciate: null, physical: true, requestable: false, byod: null, archived: null,
    expected_checkin: null, last_audit_date: null, next_audit_date: null, notes: null, image_url: null, tags: [],
};

describe('AssetForm assignee field', () => {
    it('renders a user assignee as a UserPicker with an inline create action', () => {
        render(<AssetForm initial={assignedToUser} options={options} submitUrl="/app/assets/a1" method="put" />);
        expect(screen.getByText('Assigned to')).toBeInTheDocument();
        // The UserPicker's in-field "+" create action is present.
        expect(screen.getByRole('button', { name: 'Neuen Benutzer anlegen' })).toBeInTheDocument();
    });

    it('opens the create dialog from the user picker without submitting the form', () => {
        render(<AssetForm initial={assignedToUser} options={options} submitUrl="/app/assets/a1" method="put" />);
        fireEvent.click(screen.getByRole('button', { name: 'Neuen Benutzer anlegen' }));
        expect(screen.getByLabelText(/vorname/i)).toBeInTheDocument();
        expect(submit).not.toHaveBeenCalled();
    });
});
