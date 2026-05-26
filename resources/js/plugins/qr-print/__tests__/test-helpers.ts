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
