import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/components/qr-print/qr-print-modal', () => ({ QrPrintModal: () => null }));

import QrGenerator from '../index';

describe('QrGenerator', () => {
    it('download link reflects the amount', () => {
        render(<QrGenerator />);
        const link = screen.getByRole('link', { name: /TXT/i });
        expect(link).toHaveAttribute('href', '/app/qr-generator/download?amount=20');
    });
});
