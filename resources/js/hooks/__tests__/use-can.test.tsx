import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({ usePage: () => page }));

import { useCan } from '../use-can';

describe('useCan', () => {
    it('returns true only for granted keys', () => {
        page.props = { auth: { can: { 'assets.view': true, 'assets.edit': false } } };
        const { result } = renderHook(() => useCan());
        expect(result.current('assets.view')).toBe(true);
        expect(result.current('assets.edit')).toBe(false);
        expect(result.current('unknown.key')).toBe(false);
    });

    it('treats the guest shape (empty array) and a missing auth prop as no permissions', () => {
        page.props = { auth: { user: null, can: [] } };
        expect(renderHook(() => useCan()).result.current('assets.view')).toBe(false);
        page.props = {};
        expect(renderHook(() => useCan()).result.current('assets.view')).toBe(false);
    });
});
