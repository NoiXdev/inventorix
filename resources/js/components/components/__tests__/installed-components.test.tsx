import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/assets/a1', props: {} as Record<string, unknown> }));
const post = vi.hoisted(() => vi.fn());
const serverErrors = vi.hoisted(() => ({ value: {} as Record<string, string> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, string>) => {
        const [data, setData] = useState(initial);
        return {
            data, errors: serverErrors.value, processing: false,
            setData: (k: string, v: string) => setData((d) => ({ ...d, [k]: v })),
            post: (url: string, opts: unknown) => post(url, data, opts),
        };
    },
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
}));

import { InstalledComponents } from '../installed-components';

const rows = [{ id: 'r1', component_id: 'c1', name: 'RAM stick', serial: null, qty: 4, checked_out_at: '2026-11-01', note: null }];

describe('InstalledComponents', () => {
    beforeEach(() => {
        post.mockClear();
        serverErrors.value = {};
        page.props = { auth: { user: null, can: { 'components.checkin': true } } };
    });

    it('opens a dialog defaulting to the row quantity and posts the chosen quantity', () => {
        render(<InstalledComponents components={rows} empty="none" />);
        fireEvent.click(screen.getByRole('button', { name: /Check in/ }));

        const qty = screen.getByLabelText(/Quantity to check in/) as HTMLInputElement;
        expect(qty.value).toBe('4');
        expect(qty).toHaveAttribute('max', '4');

        fireEvent.change(qty, { target: { value: '1' } });
        fireEvent.click(screen.getByRole('button', { name: 'Check in' }));
        expect(post).toHaveBeenCalledWith('/app/component-assets/r1/checkin', { qty: '1', note: '' }, expect.anything());
    });

    it('shows the server quantity error', () => {
        serverErrors.value = { qty: 'Only 4 of this component are installed here.' };
        render(<InstalledComponents components={rows} empty="none" />);
        fireEvent.click(screen.getByRole('button', { name: /Check in/ }));
        expect(screen.getByText('Only 4 of this component are installed here.')).toBeInTheDocument();
    });
});
