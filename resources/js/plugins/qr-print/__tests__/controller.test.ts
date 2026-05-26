import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ensureImageDataPolyfill, makeWhiteImageData } from './test-helpers';
import { getRollById } from '../dk-rolls';
import type { LabelItem } from '../types';

// Mock composeLabel: happy-dom cannot render canvas, and Task 11 tests the
// controller's orchestration, not the layout (covered by Task 16 smoke test).
// Return a tiny synthetic all-white ImageData large enough for buildJob to
// produce a realistic byte stream.
vi.mock('../layout', () => ({
    composeLabel: vi.fn(async () => {
        ensureImageDataPolyfill();
        // 306px wide matches dk-11209 printWidthPx; 10 rows is enough for raster lines.
        return makeWhiteImageData(306, 10);
    }),
}));

// Import controller AFTER vi.mock so the mock is applied. Vitest hoists vi.mock
// calls, but importing here makes the intent explicit.
import { PrintController } from '../controller';

describe('PrintController', () => {
    beforeEach(() => {
        const writes: Uint8Array[] = [];
        const fakeDevice = {
            vendorId: 0x04f9, productId: 0x209b,
            configuration: {
                configurationValue: 1,
                interfaces: [{ alternate: { endpoints: [
                    { endpointNumber: 1, direction: 'out' },
                ] } }],
            },
            async open() {}, async selectConfiguration() {}, async claimInterface() {},
            async releaseInterface() {}, async close() {},
            async transferOut(_ep: number, data: BufferSource) {
                const view = data instanceof ArrayBuffer ? new Uint8Array(data) : new Uint8Array((data as ArrayBufferView).buffer);
                writes.push(view);
                return { bytesWritten: view.byteLength, status: 'ok' as const };
            },
            _writes: writes,
        };
        (globalThis as unknown as Record<string, unknown>).navigator = {
            usb: {
                requestDevice: vi.fn().mockResolvedValue(fakeDevice),
                getDevices: vi.fn().mockResolvedValue([fakeDevice]),
            },
        };
        (globalThis as unknown as Record<string, unknown>).__fakeDevice = fakeDevice;
    });

    it('prints 3 labels and reports progress 3 times', async () => {
        const items: LabelItem[] = [
            { uuid: 'aaaaaaaa-0000-0000-0000-000000000001' },
            { uuid: 'aaaaaaaa-0000-0000-0000-000000000002' },
            { uuid: 'aaaaaaaa-0000-0000-0000-000000000003' },
        ];
        const controller = new PrintController();
        // Pair + open via the fake navigator before printing.
        await controller.pair();
        const progress: number[] = [];
        await controller.print({
            items,
            roll: getRollById('dk-11209'),
            layout: 'qr-only',
        }, (p) => progress.push(p.completedLabels));

        const fakeDevice = (globalThis as unknown as { __fakeDevice: { _writes: Uint8Array[] } }).__fakeDevice;
        const totalWritten = fakeDevice._writes.reduce((s, w) => s + w.length, 0);
        expect(totalWritten).toBeGreaterThan(0);
        expect(progress[progress.length - 1]).toBe(3);
    });
});
