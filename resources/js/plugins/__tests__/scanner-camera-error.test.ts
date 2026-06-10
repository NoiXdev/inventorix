import { describe, it, expect } from 'vitest';
import { classifyCameraError } from '../scanner-camera-error';

describe('classifyCameraError', () => {
    it('classifies permission errors', () => {
        expect(classifyCameraError({ name: 'NotAllowedError' })).toBe('permission');
        expect(classifyCameraError({ name: 'SecurityError' })).toBe('permission');
        expect(classifyCameraError({ name: 'PermissionDeniedError' })).toBe('permission');
    });

    it('classifies missing-camera errors', () => {
        expect(classifyCameraError({ name: 'NotFoundError' })).toBe('not_found');
        expect(classifyCameraError({ name: 'DevicesNotFoundError' })).toBe('not_found');
    });

    it('falls back to generic for anything else', () => {
        expect(classifyCameraError({ name: 'NotReadableError' })).toBe('generic');
        expect(classifyCameraError(new Error('boom'))).toBe('generic');
        expect(classifyCameraError(null)).toBe('generic');
        expect(classifyCameraError(undefined)).toBe('generic');
        expect(classifyCameraError('string error')).toBe('generic');
    });
});
