import { describe, it, expect } from 'vitest';
import { DK_ROLLS, getRollById } from '../dk-rolls';

describe('dk-rolls', () => {
    it('exports the enabled rolls', () => {
        // dk-11209 / dk-11201 are currently commented out in dk-rolls.ts; only dk-22205 is enabled.
        expect(DK_ROLLS.map(r => r.id)).toEqual(['dk-22205']);
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
