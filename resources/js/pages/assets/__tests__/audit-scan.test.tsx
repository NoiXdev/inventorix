import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('axios', () => ({
    default: {
        post: (...a: unknown[]) => post(...a),
        isAxiosError: (e: unknown): boolean => !!e && typeof e === 'object' && 'response' in e,
    },
}));
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import AuditScan from '../audit-scan';

const scan = (code: string) => {
    const input = screen.getByLabelText('Asset tag or serial');
    fireEvent.change(input, { target: { value: code } });
    fireEvent.keyDown(input, { key: 'Enter' });
};

describe('Audit scan page', () => {
    beforeEach(() => post.mockReset());

    it('posts each scan with the shared fields and lists the result', async () => {
        post.mockResolvedValueOnce({ data: { id: 'a1', name: 'Laptop 1', asset_tag: 'INV-1', location: 'Lager', next_audit_date: '2027-11-28' } });
        render(<AuditScan locationOptions={[]} nextAuditDate="2027-11-28" />);

        scan('INV-1');

        await waitFor(() => expect(screen.getByRole('link', { name: 'Laptop 1' })).toHaveAttribute('href', '/app/assets/a1'));
        expect(post).toHaveBeenCalledWith('/app/assets/audit/scan', { code: 'INV-1', location_id: '', next_audit_date: '2027-11-28', note: '' });
        expect(screen.getByText('1 asset audited in this session.')).toBeInTheDocument();
        expect(screen.getByLabelText('Asset tag or serial')).toHaveValue('');
    });

    it('shows the server message for a failed scan and does not count it', async () => {
        post.mockRejectedValueOnce({ response: { status: 422, data: { message: 'No asset with the tag or serial “X”.' } } });
        render(<AuditScan locationOptions={[]} nextAuditDate={null} />);

        scan('X');

        const results = await screen.findByRole('list', { name: 'Scan results' });
        expect(within(results).getByText('No asset with the tag or serial “X”.')).toBeInTheDocument();
        expect(screen.getByText('0 assets audited in this session.')).toBeInTheDocument();
    });

    it('queues scans made while a request runs and sends them in order', async () => {
        let release: (v: unknown) => void = () => {};
        post.mockImplementationOnce(() => new Promise((r) => { release = r; }))
            .mockResolvedValueOnce({ data: { id: 'a2', name: 'Laptop 2', asset_tag: 'INV-2', location: null, next_audit_date: null } });
        render(<AuditScan locationOptions={[]} nextAuditDate={null} />);

        scan('INV-1');
        scan('INV-2');
        expect(screen.getByText(/1 waiting/)).toBeInTheDocument();
        release({ data: { id: 'a1', name: 'Laptop 1', asset_tag: 'INV-1', location: null, next_audit_date: null } });

        await waitFor(() => expect(screen.getByText('2 assets audited in this session.')).toBeInTheDocument());
        expect(post.mock.calls.map((c) => (c[1] as { code: string }).code)).toEqual(['INV-1', 'INV-2']);
    });

    it('ignores an empty scan', () => {
        render(<AuditScan locationOptions={[]} nextAuditDate={null} />);
        scan('   ');
        expect(post).not.toHaveBeenCalled();
    });
});
