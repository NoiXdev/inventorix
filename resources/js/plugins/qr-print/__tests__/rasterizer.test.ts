import { describe, it, expect } from 'vitest';
import { rasterize } from '../rasterizer';
import { getRollById } from '../dk-rolls';

// happy-dom does not currently expose a constructible ImageData. Polyfill a
// minimal shape sufficient for the rasterizer (it reads width/height/data).
if (typeof globalThis.ImageData === 'undefined') {
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

// Helper: build an ImageData with a black-and-white pattern.
// Pixel order: RGBA, 4 bytes per pixel.
function makeImageData(width: number, height: number, blackPixels: Array<[number, number]>): ImageData {
    const data = new Uint8ClampedArray(width * height * 4);
    // Fill white
    for (let i = 0; i < data.length; i += 4) {
        data[i] = 255; data[i + 1] = 255; data[i + 2] = 255; data[i + 3] = 255;
    }
    // Set black pixels
    for (const [x, y] of blackPixels) {
        const idx = (y * width + x) * 4;
        data[idx] = 0; data[idx + 1] = 0; data[idx + 2] = 0; data[idx + 3] = 255;
    }
    return new ImageData(data, width, height);
}

describe('rasterizer', () => {
    const roll = getRollById('dk-11209');

    it('produces one Uint8Array per raster line', () => {
        const img = makeImageData(roll.printWidthPx, 10, []);
        const lines = rasterize(img, roll);
        expect(lines.length).toBe(10);
    });

    it('every line is exactly 90 bytes (720 pins / 8)', () => {
        const img = makeImageData(roll.printWidthPx, 5, []);
        const lines = rasterize(img, roll);
        for (const line of lines) {
            expect(line.length).toBe(90);
        }
    });

    it('all-white image rasterizes to all-zero bytes', () => {
        const img = makeImageData(roll.printWidthPx, 3, []);
        const lines = rasterize(img, roll);
        for (const line of lines) {
            expect(Array.from(line)).toEqual(new Array(90).fill(0));
        }
    });

    it('a single black pixel at (0,0) sets the MSB of the first printable byte', () => {
        const img = makeImageData(roll.printWidthPx, 1, [[0, 0]]);
        const lines = rasterize(img, roll);
        expect(lines[0][roll.printableStartByte]).toBe(0b10000000);
        // All other bytes must be zero
        for (let i = 0; i < 90; i++) {
            if (i !== roll.printableStartByte) {
                expect(lines[0][i]).toBe(0);
            }
        }
    });

    it('eight horizontally adjacent black pixels pack into a single 0xFF byte', () => {
        const img = makeImageData(roll.printWidthPx, 1, [
            [0, 0], [1, 0], [2, 0], [3, 0], [4, 0], [5, 0], [6, 0], [7, 0],
        ]);
        const lines = rasterize(img, roll);
        expect(lines[0][roll.printableStartByte]).toBe(0xff);
    });
});
