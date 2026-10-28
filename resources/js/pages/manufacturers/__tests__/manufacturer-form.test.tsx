import { render, screen } from '@testing-library/react';
import { describe, expect, it, beforeAll, vi } from 'vitest';
import { ManufacturerForm } from '../manufacturer-form';

beforeAll(() => {
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
});

const initial = {
    id: 'm1',
    name: 'Acme',
    url: 'https://acme.test',
    support_url: 'https://acme.test/support',
    warranty_lookup_url: 'https://acme.test/warranty',
    support_phone: '+49 30 123456',
    support_email: 'support@acme.test',
    notes: 'Contract to 2027.',
    image_url: '/app/manufacturers/m1/image',
};

describe('ManufacturerForm', () => {
    it('renders every detail field', () => {
        render(<ManufacturerForm submitUrl="/app/manufacturers" method="post" />);

        for (const label of ['Name', 'Logo', 'Website', 'Support page', 'Warranty lookup', 'Phone', 'Email', 'Notes']) {
            expect(screen.getByLabelText(new RegExp(`^${label}`))).toBeInTheDocument();
        }
    });

    it('prefills from the given manufacturer', () => {
        render(<ManufacturerForm initial={initial} submitUrl="/app/manufacturers/m1" method="put" />);

        expect(screen.getByLabelText(/^Name/)).toHaveValue('Acme');
        expect(screen.getByLabelText(/^Website/)).toHaveValue('https://acme.test');
        expect(screen.getByLabelText(/^Warranty lookup/)).toHaveValue('https://acme.test/warranty');
        expect(screen.getByLabelText(/^Email/)).toHaveValue('support@acme.test');
        expect(screen.getByLabelText(/^Notes/)).toHaveValue('Contract to 2027.');
    });

    it('shows the stored logo', () => {
        render(<ManufacturerForm initial={initial} submitUrl="/app/manufacturers/m1" method="put" />);

        expect(screen.getByRole('img')).toHaveAttribute('src', '/app/manufacturers/m1/image');
    });

    it('starts empty when creating', () => {
        render(<ManufacturerForm submitUrl="/app/manufacturers" method="post" />);

        expect(screen.getByLabelText(/^Name/)).toHaveValue('');
        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });
});
