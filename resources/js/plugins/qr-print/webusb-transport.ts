export const BROTHER_VID = 0x04f9;
export const QL800_PID = 0x209b;
const CHUNK_SIZE = 16 * 1024;

type ProgressCallback = (bytesWritten: number) => void;

interface UsbLikeDevice {
    vendorId: number;
    productId: number;
    configuration: {
        configurationValue: number;
        interfaces: Array<{ alternate: { endpoints: Array<{ endpointNumber: number; direction: string }> } }>;
    } | null;
    open(): Promise<void>;
    selectConfiguration(v: number): Promise<void>;
    claimInterface(n: number): Promise<void>;
    releaseInterface(n: number): Promise<void>;
    close(): Promise<void>;
    transferOut(endpoint: number, data: BufferSource): Promise<{ bytesWritten: number; status: string }>;
}

interface UsbLikeNavigator {
    requestDevice(options: { filters: Array<{ vendorId: number; productId?: number }> }): Promise<UsbLikeDevice>;
    getDevices(): Promise<UsbLikeDevice[]>;
}

function usb(): UsbLikeNavigator {
    const nav = (globalThis as unknown as { navigator?: { usb?: UsbLikeNavigator } }).navigator;
    if (!nav?.usb) {
        throw new Error('WebUSB nicht verfügbar');
    }
    return nav.usb;
}

export function isWebUsbAvailable(): boolean {
    const nav = (globalThis as unknown as { navigator?: { usb?: unknown } }).navigator;
    return !!nav?.usb;
}

export class WebUsbTransport {
    private device: UsbLikeDevice | null = null;
    private outEndpoint = 0;

    async pair(): Promise<void> {
        const device = await usb().requestDevice({ filters: [{ vendorId: BROTHER_VID, productId: QL800_PID }] });
        if (device.vendorId !== BROTHER_VID || device.productId !== QL800_PID) {
            throw new Error('Gerät ist kein Brother QL-800');
        }
        this.device = device;
    }

    async findPaired(): Promise<boolean> {
        const devices = await usb().getDevices();
        const match = devices.find(d => d.vendorId === BROTHER_VID && d.productId === QL800_PID);
        if (match) {
            this.device = match;
            return true;
        }
        return false;
    }

    async open(): Promise<void> {
        if (!this.device) throw new Error('Kein Drucker verbunden');
        await this.device.open();
        if (!this.device.configuration) {
            await this.device.selectConfiguration(1);
        }
        await this.device.claimInterface(0);

        const endpoints = this.device.configuration?.interfaces[0]?.alternate.endpoints ?? [];
        const out = endpoints.find(e => e.direction === 'out');
        if (!out) throw new Error('Kein OUT-Endpoint gefunden');
        this.outEndpoint = out.endpointNumber;
    }

    async send(bytes: Uint8Array, onProgress?: ProgressCallback): Promise<void> {
        if (!this.device) throw new Error('Kein Drucker verbunden');
        let offset = 0;
        while (offset < bytes.length) {
            const end = Math.min(offset + CHUNK_SIZE, bytes.length);
            // Allocate a fresh buffer per chunk so the underlying ArrayBuffer matches the chunk size.
            // Some USB stacks (and our test fake) inspect data.buffer, not the view's byteOffset/byteLength.
            const chunk = new Uint8Array(end - offset);
            chunk.set(bytes.subarray(offset, end));
            const result = await this.device.transferOut(this.outEndpoint, chunk);
            if (result.status !== 'ok') {
                throw new Error(`USB Schreibfehler: ${result.status}`);
            }
            offset = end;
            onProgress?.(offset);
        }
    }

    async close(): Promise<void> {
        if (!this.device) return;
        try { await this.device.releaseInterface(0); } catch { /* ignore */ }
        try { await this.device.close(); } catch { /* ignore */ }
        this.device = null;
    }
}
