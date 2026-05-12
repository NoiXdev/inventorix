import type { RollSpec } from './types';

export const DK_ROLLS: RollSpec[] = [
    {
        id: 'dk-22205',
        label: 'DK-22205 (62mm endlos)',
        mediaTypeByte: 0x0a,
        widthMm: 62,
        lengthMm: null,
        printWidthPx: 696,
        printHeightPx: 696,
        printableStartByte: 3,
        printableEndByte: 90,
    },
    {
        id: 'dk-11209',
        label: 'DK-11209 (29 × 62 mm)',
        mediaTypeByte: 0x0b,
        widthMm: 29,
        lengthMm: 62,
        printWidthPx: 306,
        printHeightPx: 731,
        printableStartByte: 52,
        printableEndByte: 91,
    },
    {
        id: 'dk-11201',
        label: 'DK-11201 (29 × 90 mm)',
        mediaTypeByte: 0x0b,
        widthMm: 29,
        lengthMm: 90,
        printWidthPx: 306,
        printHeightPx: 1064,
        printableStartByte: 52,
        printableEndByte: 91,
    },
];

export function getRollById(id: string): RollSpec {
    const roll = DK_ROLLS.find(r => r.id === id);
    if (!roll) {
        throw new Error(`Unknown DK roll: ${id}`);
    }
    return roll;
}
