import { qrPrintModal } from './modal';

declare global {
    interface Window {
        Alpine?: { data: (name: string, factory: () => unknown) => void };
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine?.data('qrPrintModal', qrPrintModal);
});
