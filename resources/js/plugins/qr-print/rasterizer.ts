import type { RollSpec } from './types';

const BYTES_PER_LINE = 90;
const LUMINANCE_THRESHOLD = 128;

export function rasterize(image: ImageData, roll: RollSpec): Uint8Array[] {
    const { width, height, data } = image;
    if (width !== roll.printWidthPx) {
        throw new Error(`Image width ${width} does not match roll printWidthPx ${roll.printWidthPx}`);
    }

    const lines: Uint8Array[] = [];
    for (let y = 0; y < height; y++) {
        const line = new Uint8Array(BYTES_PER_LINE);
        for (let x = 0; x < width; x++) {
            const idx = (y * width + x) * 4;
            // Luminance (Rec. 601)
            const r = data[idx];
            const g = data[idx + 1];
            const b = data[idx + 2];
            const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
            if (luminance < LUMINANCE_THRESHOLD) {
                const byteIndex = roll.printableStartByte + Math.floor(x / 8);
                if (byteIndex >= BYTES_PER_LINE || byteIndex >= roll.printableEndByte) {
                    continue;
                }
                const bitIndex = 7 - (x % 8);
                line[byteIndex] |= 1 << bitIndex;
            }
        }
        lines.push(line);
    }
    return lines;
}
