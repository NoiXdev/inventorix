import type { LabelItem, LayoutKind, RollSpec } from './types';
import { generateQrMatrix } from './qr';

export async function composeLabel(
    item: LabelItem,
    roll: RollSpec,
    layout: LayoutKind,
): Promise<ImageData> {
    const canvas = document.createElement('canvas');
    canvas.width = roll.printWidthPx;
    canvas.height = roll.printHeightPx;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas 2D context unavailable');

    // White background
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';

    const effectiveLayout: LayoutKind =
        layout === 'qr-asset' && !item.metadata ? 'qr-uuid' : layout;

    const padding = Math.floor(Math.min(canvas.width, canvas.height) * 0.05);
    const matrix = await generateQrMatrix(item.uuid);

    switch (effectiveLayout) {
        case 'qr-only':
            drawQrCentered(ctx, matrix, canvas.width, canvas.height, padding);
            break;
        case 'qr-uuid':
            drawQrWithText(ctx, matrix, canvas.width, canvas.height, padding, [shortUuid(item.uuid)]);
            break;
        case 'qr-asset': {
            const lines = [item.metadata!.modelName, item.metadata!.serial];
            drawQrWithText(ctx, matrix, canvas.width, canvas.height, padding, lines);
            break;
        }
    }

    return ctx.getImageData(0, 0, canvas.width, canvas.height);
}

function drawQrCentered(
    ctx: CanvasRenderingContext2D,
    matrix: boolean[][],
    canvasW: number,
    canvasH: number,
    padding: number,
): void {
    const available = Math.min(canvasW, canvasH) - 2 * padding;
    const modulePx = Math.floor(available / matrix.length);
    const qrSize = modulePx * matrix.length;
    const offsetX = Math.floor((canvasW - qrSize) / 2);
    const offsetY = Math.floor((canvasH - qrSize) / 2);
    drawMatrix(ctx, matrix, offsetX, offsetY, modulePx);
}

function drawQrWithText(
    ctx: CanvasRenderingContext2D,
    matrix: boolean[][],
    canvasW: number,
    canvasH: number,
    padding: number,
    textLines: string[],
): void {
    const textBlockHeight = Math.floor(canvasH * 0.18);
    const qrAreaHeight = canvasH - textBlockHeight - 2 * padding;
    const qrAreaWidth = canvasW - 2 * padding;
    const available = Math.min(qrAreaWidth, qrAreaHeight);
    const modulePx = Math.floor(available / matrix.length);
    const qrSize = modulePx * matrix.length;
    const offsetX = Math.floor((canvasW - qrSize) / 2);
    const offsetY = padding;
    drawMatrix(ctx, matrix, offsetX, offsetY, modulePx);

    const fontSize = Math.floor(textBlockHeight / (textLines.length + 1));
    ctx.font = `${fontSize}px sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const textStartY = offsetY + qrSize + padding;
    textLines.forEach((line, i) => {
        const truncated = truncateToWidth(ctx, line, qrAreaWidth);
        ctx.fillText(truncated, canvasW / 2, textStartY + fontSize * (i + 0.5));
    });
}

function drawMatrix(
    ctx: CanvasRenderingContext2D,
    matrix: boolean[][],
    offsetX: number,
    offsetY: number,
    modulePx: number,
): void {
    for (let y = 0; y < matrix.length; y++) {
        for (let x = 0; x < matrix.length; x++) {
            if (matrix[y][x]) {
                ctx.fillRect(offsetX + x * modulePx, offsetY + y * modulePx, modulePx, modulePx);
            }
        }
    }
}

function shortUuid(uuid: string): string {
    return uuid.slice(0, 8) + '…' + uuid.slice(-4);
}

function truncateToWidth(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string {
    if (ctx.measureText(text).width <= maxWidth) return text;
    let lo = 0, hi = text.length;
    while (lo < hi) {
        const mid = Math.floor((lo + hi + 1) / 2);
        if (ctx.measureText(text.slice(0, mid) + '…').width <= maxWidth) lo = mid; else hi = mid - 1;
    }
    return text.slice(0, lo) + '…';
}
