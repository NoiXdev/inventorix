import type { RollSpec } from '../types';

// A stable roll fixture for the engine tests (protocol / rasterizer / controller).
// These exercise the print pipeline independently of which rolls are enabled in
// the app's DK_ROLLS list, so they use this fixture rather than getRollById().
export const DK_11209: RollSpec = {
    id: 'dk-11209',
    label: 'DK-11209 (29 × 62 mm)',
    mediaTypeByte: 0x0b,
    widthMm: 29,
    lengthMm: 62,
    printWidthPx: 306,
    printHeightPx: 731,
    printableStartByte: 52,
    printableEndByte: 91,
};

// happy-dom does not currently expose a constructible ImageData. Polyfill a
// minimal shape sufficient for our tests (rasterizer reads width/height/data).
export function ensureImageDataPolyfill(): void {
    if (typeof globalThis.ImageData !== 'undefined') return;
    class ImageDataPolyfill {
        public readonly data: Uint8ClampedArray;
        public readonly width: number;
        public readonly height: number;
        public readonly colorSpace: PredefinedColorSpace = 'srgb';

        constructor(data: Uint8ClampedArray, width: number, height: number) {
            this.data = data;
            this.width = width;
            this.height = height;
        }
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (globalThis as any).ImageData = ImageDataPolyfill;
}

/**
 * Build an all-white ImageData of the given dimensions. Useful for tests that
 * exercise the rasterizer / protocol layers without needing a real canvas.
 */
export function makeWhiteImageData(width: number, height: number): ImageData {
    ensureImageDataPolyfill();
    const data = new Uint8ClampedArray(width * height * 4);
    for (let i = 0; i < data.length; i += 4) {
        data[i] = 255;
        data[i + 1] = 255;
        data[i + 2] = 255;
        data[i + 3] = 255;
    }
    return new ImageData(data, width, height);
}
