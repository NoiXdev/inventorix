import type { RollSpec } from './types';

const INVALIDATE = new Uint8Array(200); // all zeros
const INIT = new Uint8Array([0x1b, 0x40]);
const RASTER_MODE = new Uint8Array([0x1b, 0x69, 0x61, 0x01]);
const STATUS_NOTIFY = new Uint8Array([0x1b, 0x69, 0x21, 0x00]);
const VARIOUS_MODE_AUTOCUT = new Uint8Array([0x1b, 0x69, 0x4d, 0x40]);
const ADVANCED_MODE = new Uint8Array([0x1b, 0x69, 0x4b, 0x08]);
const MARGIN = new Uint8Array([0x1b, 0x69, 0x64, 0x23, 0x00]);
const COMPRESSION_OFF = new Uint8Array([0x4d, 0x00]);
const RASTER_LINE_HEADER = new Uint8Array([0x67, 0x00, 0x5a]);

export function buildJob(labels: Uint8Array[][], roll: RollSpec): Uint8Array {
    const chunks: Uint8Array[] = [INVALIDATE, INIT, RASTER_MODE, STATUS_NOTIFY];

    for (let i = 0; i < labels.length; i++) {
        const rasterLines = labels[i];
        const isLast = i === labels.length - 1;
        chunks.push(buildLabel(rasterLines, roll, i, isLast));
    }

    return concat(chunks);
}

function buildLabel(
    rasterLines: Uint8Array[],
    roll: RollSpec,
    pageIndex: number,
    isLast: boolean,
): Uint8Array {
    const printInfo = buildPrintInformation(rasterLines.length, roll, pageIndex);
    const raster = concat(rasterLines.map(line => concat([RASTER_LINE_HEADER, line])));
    const terminator = new Uint8Array([isLast ? 0x1a : 0x0c]);

    return concat([
        printInfo,
        VARIOUS_MODE_AUTOCUT,
        ADVANCED_MODE,
        MARGIN,
        COMPRESSION_OFF,
        raster,
        terminator,
    ]);
}

function buildPrintInformation(rasterCount: number, roll: RollSpec, pageIndex: number): Uint8Array {
    // ESC i z (0x1B 0x69 0x7A) + 10 bytes:
    //   flags (1) | media type (1) | width mm (1) | length mm (1) | raster count (4 LE) | page (1) | fixed 0x00 (1)
    const buf = new Uint8Array(13);
    buf[0] = 0x1b;
    buf[1] = 0x69;
    buf[2] = 0x7a;
    // Flags: only PI_RECOVER (0x80). We deliberately do NOT set PI_KIND (0x02) or
    // PI_WIDTH (0x04) so the printer doesn't reject jobs when the loaded roll's
    // magnetic-stripe identity differs from what the user picked in the modal —
    // notably with generic (non-Brother) DK-22205 equivalents like RL-B-D22205.
    // Brother's own P-touch Editor takes the same approach.
    buf[3] = 0x80;
    buf[4] = roll.mediaTypeByte;
    buf[5] = roll.widthMm;
    buf[6] = roll.lengthMm ?? 0;
    buf[7] = rasterCount & 0xff;
    buf[8] = (rasterCount >> 8) & 0xff;
    buf[9] = (rasterCount >> 16) & 0xff;
    buf[10] = (rasterCount >> 24) & 0xff;
    buf[11] = pageIndex & 0xff;
    buf[12] = 0x00;
    return buf;
}

export function buildCancelReset(): Uint8Array {
    return concat([INVALIDATE, INIT]);
}

function concat(parts: Uint8Array[]): Uint8Array {
    const total = parts.reduce((n, p) => n + p.length, 0);
    const out = new Uint8Array(total);
    let offset = 0;
    for (const p of parts) {
        out.set(p, offset);
        offset += p.length;
    }
    return out;
}
