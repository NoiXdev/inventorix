import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), transform: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/components/handover/signature-pad', () => ({ SignaturePad: () => <div data-testid="signature" /> }));

import MyAcceptance from '../acceptance';

const acceptance = { item_name: 'ThinkPad 7', user_name: 'Ada', eula_text: null, status: 'pending' as const, answered_at: null };

describe('My acceptance page', () => {
    it('shows the item, the buttons and a link back', () => {
        render(<MyAcceptance acceptance={acceptance} submitUrl="/app/my-items/acceptances/1" />);
        expect(screen.getByText('ThinkPad 7')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'I accept the item' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Back to my items' })).toHaveAttribute('href', '/app/my-items');
    });

    it('hides the buttons once answered', () => {
        render(<MyAcceptance acceptance={{ ...acceptance, status: 'accepted', answered_at: '01.11.2026 10:00' }} submitUrl="/x" />);
        expect(screen.queryByRole('button', { name: /accept the item/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Decline' })).not.toBeInTheDocument();
    });
});
