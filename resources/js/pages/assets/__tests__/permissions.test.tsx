import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/assets', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post: vi.fn(), put: vi.fn(), reset: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn(), visit: vi.fn(), delete: vi.fn(), post: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/components/qr-print/qr-print-modal', () => ({ QrPrintModal: () => null }));

import AssetsIndex from '../index';
import ShowAsset from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } }; };

const meta = { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never;
const row = { id: 'a1', name: 'Laptop', asset_tag: 'T1', status: null, category_name: null, manufacturer_name: null, model_name: 'X1', assignee_name: null, serial: null, purchase_cost: null, archived: false, created_at: null, incidents_count: 2, custom: {} };
const indexProps = { assets: { data: [row], meta }, statusOptions: [], categoryOptions: [], manufacturerOptions: [], locationOptions: [] };

describe('AssetsIndex permissions', () => {
    beforeEach(() => setCan({}));

    it('hides New, Import, Export, Bulk checkout and row Edit/Delete without permissions', () => {
        render(<AssetsIndex {...indexProps} />);
        expect(screen.queryByRole('link', { name: 'New asset' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Import' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Export' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Bulk checkout/ })).not.toBeInTheDocument();
        expect(document.querySelector('a[href="/app/assets/a1/edit"]')).toBeNull();
        expect(document.querySelector('svg.lucide-trash-2')).toBeNull();
    });

    it('shows each control with its permission', () => {
        setCan({ 'assets.create': true, import: true, 'assets.view': true, 'assets.checkout': true, 'assets.edit': true, 'assets.delete': true });
        render(<AssetsIndex {...indexProps} />);
        expect(screen.getByRole('link', { name: 'New asset' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Import' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Export' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Bulk checkout/ })).toBeInTheDocument();
        expect(document.querySelector('a[href="/app/assets/a1/edit"]')).not.toBeNull();
        expect(document.querySelector('svg.lucide-trash-2')).not.toBeNull();
    });

    it('needs both import and assets.create for Import', () => {
        setCan({ import: true });
        render(<AssetsIndex {...indexProps} />);
        expect(screen.queryByRole('button', { name: 'Import' })).not.toBeInTheDocument();
    });

    it('hides the incidents column without incidents.view', () => {
        render(<AssetsIndex {...indexProps} />);
        expect(screen.queryByText('Incidents')).not.toBeInTheDocument();
        setCan({ 'incidents.view': true });
        render(<AssetsIndex {...indexProps} />);
        expect(screen.getByText('Incidents')).toBeInTheDocument();
    });

    it('hides the bulk "Check out (n)" button without assets.checkout', () => {
        render(<AssetsIndex {...indexProps} />);
        fireEvent.click(screen.getAllByRole('checkbox')[1]);
        expect(screen.getByText(/QR drucken/)).toBeInTheDocument();
        expect(screen.queryByText(/Check out \(1\)/)).not.toBeInTheDocument();
    });

    it('shows the bulk "Check out (n)" button with assets.checkout', () => {
        setCan({ 'assets.checkout': true });
        render(<AssetsIndex {...indexProps} />);
        fireEvent.click(screen.getAllByRole('checkbox')[1]);
        expect(screen.getByText(/Check out \(1\)/)).toBeInTheDocument();
    });
});

const asset = {
    id: 'a1', name: 'Laptop', asset_tag: 'T1', status: null, category_name: null, manufacturer_name: null, model_name: 'X1',
    assignee: null, location_name: null, rtd_location_name: null, supplier_name: null, serial: null, purchase_date: null, purchase_cost: null,
    order_number: null, warranty_months: null, warranty_end: null, asset_eol_date: null, eol_explicit: false, depreciate: null, physical: true,
    requestable: false, byod: null, archived: false, notes: null, image_url: null, last_checkout: null, last_checkin: null, expected_checkin: null,
    last_audit_date: null, next_audit_date: null, checkout_counter: 0, checkin_counter: 0, requests_counter: 0, tags: [], incidentsCount: 1,
    created_at: null, updated_at: null,
};
const showProps = { asset, attachments: [], attachmentCategoryOptions: [], incidents: [], history: [], customFields: [], checkoutBlocker: null };

describe('ShowAsset permissions', () => {
    beforeEach(() => setCan({}));

    it('hides Edit, Check out and the Incidents tab without permissions', () => {
        render(<ShowAsset {...showProps} />);
        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Check out/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Check in/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('tab', { name: /Incidents/ })).not.toBeInTheDocument();
    });

    it('shows Edit and Check out with their permissions', () => {
        setCan({ 'assets.edit': true, 'assets.checkout': true, 'incidents.view': true });
        render(<ShowAsset {...showProps} />);
        expect(screen.getByRole('link', { name: 'Edit' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Check out/ })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: /Incidents \(1\)/ })).toBeInTheDocument();
    });

    it('shows Audit only with assets.audit', () => {
        const { unmount } = render(<ShowAsset {...showProps} />);
        expect(screen.queryByRole('button', { name: /Audit/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'assets.audit': true });
        render(<ShowAsset {...showProps} />);
        expect(screen.getByRole('button', { name: /Audit/ })).toBeInTheDocument();
    });

    it('shows Check in only with assets.checkin when checked out', () => {
        const checkedOut = { ...showProps, asset: { ...asset, assignee: { type: 'user' as const, name: 'Ada', url: null } } };
        setCan({ 'assets.checkout': true });
        const { unmount } = render(<ShowAsset {...checkedOut} />);
        expect(screen.queryByRole('button', { name: /Check in/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'assets.checkin': true });
        render(<ShowAsset {...checkedOut} />);
        expect(screen.getByRole('button', { name: /Check in/ })).toBeInTheDocument();
    });

    it('hides the upload form and delete buttons without assets.files', () => {
        const att = { id: 'f1', type: 'pdf', category_label: null, title: 'Doc', note: null, original_name: 'doc.pdf', size: 1, size_label: '1 B', uploaded_by_name: null, created_at: null, url: '/x' };
        const { unmount } = render(<ShowAsset {...showProps} attachments={[att]} />);
        // radix tabs render only the active tab; open attachments
        fireEvent.mouseDown(screen.getByRole('tab', { name: 'Attachments' }));
        fireEvent.click(screen.getByRole('tab', { name: 'Attachments' }));
        expect(screen.queryByRole('button', { name: 'Upload' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Delete doc.pdf/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'assets.files': true });
        render(<ShowAsset {...showProps} attachments={[att]} />);
        fireEvent.mouseDown(screen.getByRole('tab', { name: 'Attachments' }));
        fireEvent.click(screen.getByRole('tab', { name: 'Attachments' }));
        expect(screen.getByRole('button', { name: 'Upload' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Delete doc.pdf/ })).toBeInTheDocument();
    });
});
