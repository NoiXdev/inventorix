import { describe, it, expect } from 'vitest';
import { DK_ROLLS, getRollById } from '../dk-rolls';

describe('dk-rolls', () => {
    it('exports the three v1 rolls', () => {
        expect(DK_ROLLS.map(r => r.id)).toEqual(['dk-22205', 'dk-11209', 'dk-11201']);
    });

    it('DK-11209 has expected pin geometry', () => {
        const roll = getRollById('dk-11209');
        expect(roll.widthMm).toBe(29);
        expect(roll.lengthMm).toBe(62);
        expect(roll.printWidthPx).toBe(306);
        expect(roll.printHeightPx).toBe(731);
        expect(roll.mediaTypeByte).toBe(0x0b);
    });

    it('DK-22205 is continuous (lengthMm null)', () => {
        const roll = getRollById('dk-22205');
        expect(roll.lengthMm).toBeNull();
        expect(roll.printWidthPx).toBe(696);
    });

    it('getRollById throws on unknown id', () => {
        expect(() => getRollById('nope')).toThrow();
    });
});
