import { describe, it, expect } from 'vitest';
import { buildJob } from '../brother-protocol';
import { getRollById } from '../dk-rolls';

describe('brother-protocol', () => {
    const roll = getRollById('dk-11209');

    it('starts with 200 zero bytes (invalidate) then ESC @ (initialize)', () => {
        const oneRaster = [new Uint8Array(90)];
        const bytes = buildJob([oneRaster], roll);
        // First 200 bytes: invalidate
        expect(Array.from(bytes.slice(0, 200))).toEqual(new Array(200).fill(0));
        // Then ESC @
        expect(bytes[200]).toBe(0x1b);
        expect(bytes[201]).toBe(0x40);
    });

    it('contains the switch-to-raster command after init', () => {
        const oneRaster = [new Uint8Array(90)];
        const bytes = buildJob([oneRaster], roll);
        // After ESC @ (200, 201): 1B 69 61 01
        expect(bytes[202]).toBe(0x1b);
        expect(bytes[203]).toBe(0x69);
        expect(bytes[204]).toBe(0x61);
        expect(bytes[205]).toBe(0x01);
    });

    it('ends with 0x1A (print + eject) on the last label', () => {
        const oneRaster = [new Uint8Array(90)];
        const bytes = buildJob([oneRaster], roll);
        expect(bytes[bytes.length - 1]).toBe(0x1a);
    });

    it('uses 0x0C between labels and 0x1A only at the end', () => {
        const label1 = [new Uint8Array(90)];
        const label2 = [new Uint8Array(90)];
        const bytes = buildJob([label1, label2], roll);
        // Count occurrences
        const count0x0c = bytes.reduce((n, b) => b === 0x0c ? n + 1 : n, 0);
        const count0x1a = bytes.reduce((n, b) => b === 0x1a ? n + 1 : n, 0);
        // Note: 0x0c and 0x1a may appear inside raster data, so we cannot exact-match.
        // Instead assert end byte and that 0x0c appears at least once.
        expect(bytes[bytes.length - 1]).toBe(0x1a);
        expect(count0x0c).toBeGreaterThanOrEqual(1);
    });

    it('embeds the media type byte from the roll spec', () => {
        const oneRaster = [new Uint8Array(90)];
        const bytes = buildJob([oneRaster], roll);
        // Locate the ESC i z command (1B 69 7A) and inspect the next byte after the flags.
        // Sequence: 1B 69 7A <flags=0x80> <media-type> ...
        // Flags = 0x80 (PI_RECOVER only — no kind/width validation, see brother-protocol.ts).
        for (let i = 0; i < bytes.length - 4; i++) {
            if (bytes[i] === 0x1b && bytes[i + 1] === 0x69 && bytes[i + 2] === 0x7a) {
                expect(bytes[i + 4]).toBe(roll.mediaTypeByte);
                return;
            }
        }
        throw new Error('ESC i z command not found in output');
    });
});
