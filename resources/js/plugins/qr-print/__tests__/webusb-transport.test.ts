import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WebUsbTransport, BROTHER_VID, QL800_PID } from '../webusb-transport';

interface FakeDevice {
    vendorId: number; productId: number;
    opened: boolean; claimed: boolean;
    configuration: { configurationValue: number; interfaces: Array<{ alternate: { endpoints: Array<{ endpointNumber: number; direction: string }> } }> } | null;
    writes: Uint8Array[];
    open: () => Promise<void>;
    selectConfiguration: (v: number) => Promise<void>;
    claimInterface: (n: number) => Promise<void>;
    releaseInterface: (n: number) => Promise<void>;
    close: () => Promise<void>;
    transferOut: (ep: number, data: BufferSource) => Promise<{ bytesWritten: number; status: string }>;
}

function makeFakeDevice(): FakeDevice {
    const writes: Uint8Array[] = [];
    return {
        vendorId: BROTHER_VID, productId: QL800_PID,
        opened: false, claimed: false,
        configuration: {
            configurationValue: 1,
            interfaces: [{ alternate: { endpoints: [
                { endpointNumber: 1, direction: 'out' },
                { endpointNumber: 2, direction: 'in' },
            ] } }],
        },
        writes,
        async open() { this.opened = true; },
        async selectConfiguration() {},
        async claimInterface() { this.claimed = true; },
        async releaseInterface() { this.claimed = false; },
        async close() { this.opened = false; },
        async transferOut(_ep, data) {
            const view = data instanceof ArrayBuffer ? new Uint8Array(data) : new Uint8Array((data as ArrayBufferView).buffer);
            writes.push(view);
            return { bytesWritten: view.byteLength, status: 'ok' };
        },
    };
}

describe('WebUsbTransport', () => {
    let fakeUsb: { requestDevice: ReturnType<typeof vi.fn>; getDevices: ReturnType<typeof vi.fn> };
    let device: FakeDevice;

    beforeEach(() => {
        device = makeFakeDevice();
        fakeUsb = {
            requestDevice: vi.fn().mockResolvedValue(device),
            getDevices: vi.fn().mockResolvedValue([device]),
        };
        (globalThis as unknown as { navigator: { usb: typeof fakeUsb } }).navigator = { usb: fakeUsb };
    });

    it('opens, claims, sends bytes, and reports total written via progress callback', async () => {
        const transport = new WebUsbTransport();
        await transport.pair();
        await transport.open();

        const payload = new Uint8Array(20_000); // forces chunking
        payload.fill(0xab);
        const progress: number[] = [];
        await transport.send(payload, (n) => progress.push(n));

        const totalWritten = device.writes.reduce((s, w) => s + w.length, 0);
        expect(totalWritten).toBe(20_000);
        expect(progress[progress.length - 1]).toBe(20_000);
    });

    it('release + close on close()', async () => {
        const transport = new WebUsbTransport();
        await transport.pair();
        await transport.open();
        await transport.close();
        expect(device.claimed).toBe(false);
        expect(device.opened).toBe(false);
    });

    it('throws if user picks a non-QL-800 device', async () => {
        device.productId = 0x1234;
        const transport = new WebUsbTransport();
        await expect(transport.pair()).rejects.toThrow(/QL-800/);
    });
});
