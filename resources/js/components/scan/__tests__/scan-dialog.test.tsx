import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { get: (...a: unknown[]) => get(...a) } }));
const start = vi.fn().mockResolvedValue(undefined);
vi.mock('qr-scanner', () => ({ default: class { start = start; stop = vi.fn(); destroy = vi.fn(); constructor() {} } }));

import { ScanDialog } from '../scan-dialog';

describe('ScanDialog', () => {
    it('manual submit navigates to resolve', () => {
        get.mockClear();
        render(<ScanDialog open onClose={() => {}} />);
        fireEvent.change(screen.getByPlaceholderText(/UUID/i), { target: { value: 'abc-123' } });
        fireEvent.click(screen.getByRole('button', { name: /öffnen|open/i }));
        expect(get).toHaveBeenCalledWith('/app/scan/resolve', { code: 'abc-123' });
    });
});
