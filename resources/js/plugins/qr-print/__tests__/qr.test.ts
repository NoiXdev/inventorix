import { describe, it, expect } from 'vitest';
import { generateQrMatrix } from '../qr';

describe('qr', () => {
    it('encodes a UUID as a square boolean matrix at ECC M', async () => {
        const uuid = '550e8400-e29b-41d4-a716-446655440000';
        const matrix = await generateQrMatrix(uuid);
        expect(matrix.length).toBeGreaterThan(20);
        expect(matrix.length).toBe(matrix[0].length);
        expect(matrix.every(row => row.length === matrix.length)).toBe(true);
    });

    it('produces the same matrix for the same input (deterministic)', async () => {
        const a = await generateQrMatrix('test-uuid');
        const b = await generateQrMatrix('test-uuid');
        expect(a).toEqual(b);
    });
});
