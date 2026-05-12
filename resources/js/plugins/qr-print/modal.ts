import { DK_ROLLS, getRollById } from './dk-rolls';
import { PrintController } from './controller';
import { composeLabel } from './layout';
import { isWebUsbAvailable } from './webusb-transport';
import type { LabelItem, LayoutKind, OpenEventDetail } from './types';

const STORAGE_KEY_ROLL = 'qrPrint.lastRollId';
const STORAGE_KEY_LAYOUT = 'qrPrint.lastLayout';

interface ModalState {
    isOpen: boolean;
    items: LabelItem[];
    rollId: string;
    layout: LayoutKind;
    paired: boolean;
    pairing: boolean;
    webUsbSupported: boolean;
    printing: boolean;
    completed: number;
    total: number;
    errorMessage: string | null;
    statusMessage: string | null;
    init(): void;
    open(detail: OpenEventDetail): Promise<void>;
    close(): void;
    pair(): Promise<void>;
    renderPreview(): Promise<void>;
    print(): Promise<void>;
    cancel(): Promise<void>;
    canUseAssetLayout(): boolean;
    allLayouts(): { value: LayoutKind; label: string; disabled: boolean }[];
    allRolls(): typeof DK_ROLLS;
}

export function qrPrintModal(): ModalState {
    const controller = new PrintController();

    return {
        isOpen: false,
        items: [],
        rollId: localStorage.getItem(STORAGE_KEY_ROLL) ?? 'dk-11209',
        layout: (localStorage.getItem(STORAGE_KEY_LAYOUT) as LayoutKind | null) ?? 'qr-uuid',
        paired: false,
        pairing: false,
        webUsbSupported: isWebUsbAvailable(),
        printing: false,
        completed: 0,
        total: 0,
        errorMessage: null,
        statusMessage: null,

        init() {
            window.addEventListener('qr-print:open', async (event) => {
                const detail = (event as CustomEvent<OpenEventDetail>).detail;
                await this.open(detail);
            });
            if (this.webUsbSupported) {
                controller.tryReconnect().then((found) => { this.paired = found; });
            }
        },

        async open(detail: OpenEventDetail) {
            this.items = detail.items;
            this.total = detail.items.length;
            this.completed = 0;
            this.errorMessage = null;
            this.statusMessage = null;
            if (detail.defaults?.rollId) this.rollId = detail.defaults.rollId;
            if (detail.defaults?.layout) this.layout = detail.defaults.layout;
            if (!this.canUseAssetLayout() && this.layout === 'qr-asset') this.layout = 'qr-uuid';
            this.isOpen = true;
            await this.renderPreview();
        },

        close() {
            this.isOpen = false;
            this.items = [];
        },

        async pair() {
            this.pairing = true;
            this.errorMessage = null;
            try {
                await controller.pair();
                this.paired = true;
            } catch (err) {
                if ((err as Error).message?.includes('No device selected')) {
                    // User dismissed prompt — silent
                } else {
                    this.errorMessage = (err as Error).message;
                }
            } finally {
                this.pairing = false;
            }
        },

        async renderPreview() {
            const target = document.getElementById('qr-print-preview');
            if (!target) return;
            target.innerHTML = '';
            if (!this.items[0]) return;

            const roll = getRollById(this.rollId);
            const image = await composeLabel(this.items[0], roll, this.layout);

            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            canvas.style.maxWidth = '100%';
            canvas.style.maxHeight = '320px';
            canvas.style.objectFit = 'contain';
            canvas.style.border = '1px solid #ddd';
            const ctx = canvas.getContext('2d');
            ctx?.putImageData(image, 0, 0);
            target.appendChild(canvas);
        },

        async print() {
            if (!this.paired) {
                this.errorMessage = 'Drucker nicht verbunden.';
                return;
            }
            this.printing = true;
            this.errorMessage = null;
            localStorage.setItem(STORAGE_KEY_ROLL, this.rollId);
            localStorage.setItem(STORAGE_KEY_LAYOUT, this.layout);
            try {
                await controller.print(
                    { items: this.items, roll: getRollById(this.rollId), layout: this.layout },
                    (p) => { this.completed = p.completedLabels; },
                );
                this.statusMessage = `${this.total} von ${this.total} Etiketten gedruckt.`;
            } catch (err) {
                this.errorMessage = (err as Error).message;
            } finally {
                this.printing = false;
            }
        },

        async cancel() {
            await controller.cancel();
            this.statusMessage = `Gestoppt nach ${this.completed} von ${this.total} Etiketten — bis zu 2 weitere Etiketten können noch ausgegeben werden.`;
            this.printing = false;
        },

        canUseAssetLayout() {
            return this.items.length > 0 && this.items.every(i => !!i.metadata);
        },

        allLayouts() {
            return [
                { value: 'qr-only' as LayoutKind, label: 'Nur QR-Code', disabled: false },
                { value: 'qr-uuid' as LayoutKind, label: 'QR + UUID', disabled: false },
                { value: 'qr-asset' as LayoutKind, label: 'QR + Asset-Info', disabled: !this.canUseAssetLayout() },
            ];
        },

        allRolls() {
            return DK_ROLLS;
        },
    };
}
