import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const pair = vi.fn().mockResolvedValue(undefined);
const print = vi.fn().mockResolvedValue(undefined);
const tryReconnect = vi.fn().mockResolvedValue(false);

vi.mock('@/plugins/qr-print/controller', () => ({
    PrintController: class {
        pair = pair; print = print; tryReconnect = tryReconnect; cancel = vi.fn(); close = vi.fn();
    },
}));
vi.mock('@/plugins/qr-print/layout', () => ({ composeLabel: vi.fn().mockResolvedValue({ width: 1, height: 1, data: new Uint8ClampedArray(4) }) }));
let webusb = true;
vi.mock('@/plugins/qr-print/webusb-transport', () => ({ isWebUsbAvailable: () => webusb }));

import { QrPrintModal } from '../qr-print-modal';

describe('QrPrintModal', () => {
    beforeEach(() => { webusb = true; pair.mockClear(); print.mockClear(); });

    const items = [{ uuid: 'u1', metadata: { modelName: 'X1', serial: 'SN1' } }];

    it('shows a WebUSB-unsupported notice when unavailable', () => {
        webusb = false;
        render(<QrPrintModal open items={items} onClose={() => {}} />);
        expect(screen.getByText(/WebUSB|nicht unterstützt/i)).toBeInTheDocument();
    });

    it('pairs then prints', async () => {
        render(<QrPrintModal open items={items} onClose={() => {}} />);
        fireEvent.click(screen.getByRole('button', { name: /verbinden|pair/i }));
        await vi.waitFor(() => expect(pair).toHaveBeenCalled());
        fireEvent.click(await screen.findByRole('button', { name: /^drucken|print/i }));
        await vi.waitFor(() => expect(print).toHaveBeenCalled());
    });

    it('disables the asset layout when an item lacks metadata', () => {
        render(<QrPrintModal open items={[{ uuid: 'u2' }]} onClose={() => {}} />);
        const assetOption = screen.getByLabelText(/Asset-Info/i) as HTMLInputElement;
        expect(assetOption).toBeDisabled();
    });
});
