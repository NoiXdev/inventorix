import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
let transformer: ((d: Record<string, unknown>) => Record<string, unknown>) | null = null;
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setAll] = useState(initial);
        return {
            data,
            setData: (k: string, v: unknown) => setAll((d) => ({ ...d, [k]: v })),
            transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => { transformer = fn; },
            post: (url: string) => post(url, transformer ? transformer(data) : data),
            processing: false,
            errors: {},
        };
    },
}));
vi.mock('@/components/handover/signature-pad', () => ({ SignaturePad: () => <div data-testid="signature" /> }));

import AcceptanceRespond from '../respond';

const acceptance = { item_name: 'ThinkPad 7', user_name: 'Ada', eula_text: 'Handle with care.', status: 'pending' as const, answered_at: null };

describe('Acceptance page (signed link)', () => {
    beforeEach(() => { post.mockClear(); transformer = null; });

    it('shows the item and the terms and posts the decision', () => {
        render(<AcceptanceRespond acceptance={acceptance} submitUrl="/acceptance/a1?signature=x" />);
        expect(screen.getByText('ThinkPad 7')).toBeInTheDocument();
        expect(screen.getByText('Handle with care.')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Note (optional)'), { target: { value: 'Got it' } });
        fireEvent.click(screen.getByRole('button', { name: 'I accept the item and the terms' }));
        expect(post).toHaveBeenCalledWith('/acceptance/a1?signature=x', expect.objectContaining({ decision: 'accept', note: 'Got it' }));

        fireEvent.click(screen.getByRole('button', { name: 'Decline' }));
        expect(post).toHaveBeenLastCalledWith('/acceptance/a1?signature=x', expect.objectContaining({ decision: 'decline' }));
    });

    it('shows the answer once given, without buttons', () => {
        render(<AcceptanceRespond acceptance={{ ...acceptance, status: 'accepted', answered_at: '28.11.2026 10:00' }} submitUrl="/x" />);
        expect(screen.getByRole('status')).toHaveTextContent('Accepted on 28.11.2026 10:00');
        expect(screen.queryByRole('button', { name: /accept/i })).not.toBeInTheDocument();
    });
});
