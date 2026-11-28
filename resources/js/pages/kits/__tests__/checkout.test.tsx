import { useRef, useState } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setAll] = useState(initial);
        const transform = useRef<(d: T) => unknown>((d) => d);
        return {
            data,
            setData: (k: string, v: unknown) => setAll((d) => ({ ...d, [k]: v })),
            transform: (fn: (d: T) => unknown) => { transform.current = fn; },
            post: (url: string) => post(url, transform.current(data)),
            processing: false,
            errors: {},
        };
    },
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: {} }),
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import KitCheckout from '../checkout';

const props = {
    kit: { id: 'k1', name: 'New hire' },
    lines: [
        {
            id: 'l1', type: 'model' as const, name: 'ThinkPad', qty: 2, available: true, stock: 3,
            candidates: [
                { value: 'a1', label: 'INV-1' },
                { value: 'a2', label: 'INV-2' },
                { value: 'a3', label: 'INV-3' },
            ],
            preselected: ['a1', 'a2'],
        },
        { id: 'l2', type: 'license' as const, name: 'Office', qty: 1, available: true, stock: 4 },
        { id: 'l3', type: 'consumable' as const, name: 'Toner', qty: 3, available: true, stock: 10 },
    ],
    blocked: [] as string[],
    userOptions: [{ value: 'u1', label: 'Nina New' }],
    userCreateUrl: '/app/users/quick',
};

const assetSelect = (n: number) => screen.getByRole('button', { name: new RegExp(`^Select thinkpad ${n}`) });

describe('Kit check-out page', () => {
    beforeEach(() => post.mockClear());

    it('pre-fills the asset selects and lists the other lines', () => {
        render(<KitCheckout {...props} />);
        expect(assetSelect(1)).toHaveAccessibleName(/currently INV-1$/);
        expect(assetSelect(2)).toHaveAccessibleName(/currently INV-2$/);
        expect(screen.getByText('Office')).toBeInTheDocument();
        expect(screen.getByText('Toner')).toBeInTheDocument();
        expect(screen.getByText('10 in stock')).toBeInTheDocument();
    });

    it('offers only assets not chosen in another select, and submits the choice', () => {
        render(<KitCheckout {...props} />);
        fireEvent.click(assetSelect(2));
        expect(screen.queryByRole('button', { name: 'INV-1' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'INV-3' }));
        expect(assetSelect(2)).toHaveAccessibleName(/currently INV-3$/);

        fireEvent.click(screen.getByRole('button', { name: /Select user/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Nina New' }));
        fireEvent.click(screen.getByRole('button', { name: 'Check out kit' }));

        expect(post).toHaveBeenCalledWith('/app/kits/k1/checkout', { user_id: 'u1', assets: { l1: ['a1', 'a3'] }, note: '' });
    });

    it('shows blockers and disables submit', () => {
        render(<KitCheckout {...props} blocked={['You may not check out licenses.']} />);
        expect(screen.getByRole('alert')).toHaveTextContent('You may not check out licenses.');
        expect(screen.getByRole('button', { name: 'Check out kit' })).toBeDisabled();
    });
});
