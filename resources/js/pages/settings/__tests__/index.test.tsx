import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const put = vi.fn();
const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    useForm: (initial: Record<string, unknown>) => {
        let data = { ...initial };
        return {
            data,
            setData: (k: string, v: unknown) => { data[k] = v; },
            put: (...a: unknown[]) => put(...a),
            post: (...a: unknown[]) => post(...a),
            processing: false,
            errors: {},
        };
    },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import Settings from '../index';

const t = {
    general: { title: 'Allgemein', field: { app_name: 'Name' } },
    mail: {
        title: 'E-Mail', section: { from: 'Absender', smtp: 'SMTP' },
        field: { from_address: 'Von', from_name: 'Name', driver: 'Treiber', smtp_host: 'Host', smtp_port: 'Port', smtp_username: 'User', smtp_password: 'Pass' },
        test: { action: 'Test', recipient: 'An' },
    },
    storage: {
        title: 'Speicher', section: { s3: 'S3' },
        field: { key: 'Key', secret: 'Secret', region: 'Region', bucket: 'Bucket', endpoint: 'Endpoint', url: 'URL', use_path_style_endpoint: 'Path-Style-Endpoint' },
        test: { action: 'Test' },
    },
    warranty: {
        title: 'Garantie',
        field: { enabled: 'Aktiviert', recipients: 'Empfänger', lead_days: 'Vorlauftage' },
        test: { action: 'Test' },
    },
    alerts: {
        title: 'Audits & Rückgaben',
        field: {
            enabled: 'Erinnerungen aktiviert', recipients: 'Übersicht an', audit_interval_months: 'Audit-Intervall',
            audit_interval_help: 'Hilfe', audit_warning_days: 'Audit-Vorlauf', checkin_warning_days: 'Rückgabe-Vorlauf', license_warning_days: 'Lizenz-Vorlauf', help: 'Erklärung',
        },
        test: { action: 'Übersicht jetzt senden' },
    },
    auth: {
        title: 'Auth',
        multi_factor: { section: 'MFA', field: { enabled: 'Aktiviert', force: 'Erzwingen', recoverable: 'Wiederherstellbar' } },
        microsoft: { section: 'MS', field: { enabled: 'Aktiviert', client_id: 'Client-ID', client_secret: 'Client-Secret', redirect: 'Redirect', tenant: 'Tenant' } },
    },
    nav: { cluster: 'Einstellungen' },
};
const props = {
    t,
    options: { mailDrivers: [{ value: 'smtp', label: 'SMTP' }, { value: 'log', label: 'Log' }], mailSchemes: [] },
    settings: {
        general: { app_name: 'Inventorix' },
        mail: { default_mailer: 'smtp', from_address: 'a@b.de', from_name: 'Ops', smtp_host: 'h', smtp_port: 25, smtp_scheme: null, smtp_username: 'u', ses_key: null, ses_region: null, postmark_message_stream_id: null, postal_domain: null },
        storage: { key: null, region: null, bucket: null, endpoint: null, use_path_style_endpoint: false, url: null },
        warranty: { enabled: false, recipients: [], lead_days: [] },
        alerts: { enabled: true, recipients: ['it@example.test'], audit_interval_months: 12, audit_warning_days: 14, checkin_warning_days: 7, license_warning_days: 30 },
        auth: { multi_factor_enabled: false, multi_factor_force: false, multi_factor_recoverable: false, microsoft_enabled: false, microsoft_client_id: null, microsoft_redirect: null, microsoft_tenant: null },
    },
};

describe('Settings', () => {
    it('renders the General tab with the app name', () => {
        render(<Settings {...(props as any)} />);
        expect(screen.getByDisplayValue('Inventorix')).toBeInTheDocument();
    });

    it('renders the Warranty tab controls', () => {
        render(<Settings {...(props as any)} />);
        const tab = screen.getByRole('tab', { name: 'Garantie' });
        fireEvent.click(tab);
        fireEvent.focus(tab);
        expect(screen.getByLabelText(/aktiviert/i)).toBeInTheDocument();
    });

    it('renders the Audits & returns tab with its values', () => {
        render(<Settings {...(props as any)} />);
        const tab = screen.getByRole('tab', { name: 'Audits & Rückgaben' });
        fireEvent.mouseDown(tab);
        fireEvent.click(tab);
        fireEvent.focus(tab);
        expect(screen.getByLabelText('Audit-Intervall')).toHaveValue(12);
        expect(screen.getByLabelText('Rückgabe-Vorlauf')).toHaveValue(7);
        expect(screen.getByRole('button', { name: 'Übersicht jetzt senden' })).toBeInTheDocument();
    });
});
