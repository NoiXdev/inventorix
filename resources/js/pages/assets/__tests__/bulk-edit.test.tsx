import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const submit = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setAll] = useState(initial);
        return {
            data,
            setData: (k: string, v: unknown) => setAll((d) => ({ ...d, [k]: v })),
            post: (...a: unknown[]) => submit('post', ...a, data),
            put: (...a: unknown[]) => submit('put', ...a, data),
            processing: false,
            errors: {},
        };
    },
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import BulkEdit from '../bulk-edit';
import BulkCheckin from '../bulk-checkin';

const asset = (id: string, name: string, tag: string) =>
    ({ id, name, asset_tag: tag, serial: null, model_name: null, status_name: 'In Benutzung', assignee_name: 'Ada' });

describe('Bulk edit page', () => {
    beforeEach(() => submit.mockClear());

    const props = {
        availableAssets: [asset('a1', 'Laptop 1', 'INV-1'), asset('a2', 'Laptop 2', 'INV-2')],
        preselectedIds: ['a1', 'a2'], skipped: [], maxAssets: 500,
        statusOptions: [], modelOptions: [], locationOptions: [], supplierOptions: [],
    };

    it('sends only what was filled in, plus the cleared fields', () => {
        render(<BulkEdit {...props} />);
        fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-7' } });
        fireEvent.click(screen.getByLabelText('Clear Notes'));
        fireEvent.click(screen.getByRole('button', { name: 'Update 2' }));

        const [method, url, data] = submit.mock.calls[0];
        expect([method, url]).toEqual(['put', '/app/assets/bulk-edit']);
        expect(data).toMatchObject({ asset_ids: ['a1', 'a2'], order_number: 'PO-7', notes: '', clear: ['notes'], status_id: '' });
    });

    it('clearing a field empties what was typed into it', () => {
        render(<BulkEdit {...props} />);
        fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-7' } });
        fireEvent.click(screen.getByLabelText('Clear Order number'));
        fireEvent.click(screen.getByRole('button', { name: 'Update 2' }));

        expect(submit.mock.calls[0][2]).toMatchObject({ order_number: '', clear: ['order_number'] });
    });

    it('a cleared field cannot be typed into', () => {
        render(<BulkEdit {...props} />);
        fireEvent.click(screen.getByLabelText('Clear Order number'));
        expect(screen.getByLabelText('Order number')).toBeDisabled();
    });

    it('shows who has each asset', () => {
        render(<BulkEdit {...props} />);
        expect(screen.getAllByText('Ada')).toHaveLength(2);
    });
});

describe('Bulk check-in page', () => {
    beforeEach(() => submit.mockClear());

    it('preselects storage and posts the selected assets', () => {
        render(<BulkCheckin availableAssets={[asset('a1', 'Laptop 1', 'INV-1')]} preselectedIds={['a1']} skipped={[]} maxAssets={500}
            statusOptions={[{ value: 's-store', label: 'Lager' }]} locationOptions={[]} checkinStatusId="s-store" />);
        fireEvent.click(screen.getByRole('button', { name: 'Check in 1' }));

        const [method, url, data] = submit.mock.calls[0];
        expect([method, url]).toEqual(['post', '/app/assets/bulk-checkin']);
        expect(data).toMatchObject({ asset_ids: ['a1'], status_id: 's-store', location_id: '' });
    });

    it('cannot submit without assets', () => {
        render(<BulkCheckin availableAssets={[]} preselectedIds={[]} skipped={[]} maxAssets={500}
            statusOptions={[]} locationOptions={[]} checkinStatusId="s-store" />);
        expect(screen.getByRole('button', { name: /^Check in/ })).toBeDisabled();
    });
});
