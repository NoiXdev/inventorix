import { describe, expect, it } from 'vitest';
import { buildTableUrl } from '../use-table-query';

describe('buildTableUrl', () => {
    it('sets a param and resets page to 1 when sort changes', () => {
        const url = buildTableUrl('/app/manufacturers', { search: 'acme' }, '?page=3&sort=name');
        expect(url).toContain('search=acme');
        expect(url).toContain('page=1');
    });

    it('toggles sort direction with a dash prefix', () => {
        const asc = buildTableUrl('/app/manufacturers', { sort: 'name' }, '');
        expect(asc).toContain('sort=name');
        const desc = buildTableUrl('/app/manufacturers', { sort: '-name' }, '?sort=name');
        expect(desc).toContain('sort=-name');
    });

    it('preserves unrelated existing params', () => {
        const url = buildTableUrl('/app/manufacturers', { page: '2' }, '?perPage=25');
        expect(url).toContain('perPage=25');
        expect(url).toContain('page=2');
    });
});
