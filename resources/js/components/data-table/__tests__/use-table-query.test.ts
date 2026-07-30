import { describe, expect, it } from 'vitest';
import { buildTableUrl, nextSort } from '../use-table-query';

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

    it('builds a filter param and resets page', () => {
        const url = buildTableUrl('/app/asset-models', { 'filter[manufacturer_id]': 'abc' }, '?page=4');
        expect(url).toContain('manufacturer_id');
        expect(url).toContain('abc');
        expect(url).toContain('page=1');
    });

    it('clears a filter param when value is empty', () => {
        const url = buildTableUrl('/app/asset-models', { 'filter[manufacturer_id]': '' }, '?filter%5Bmanufacturer_id%5D=abc&page=2');
        expect(url).not.toContain('abc');
    });
});

describe('nextSort', () => {
    it('sorts ascending when the column is not currently active', () => {
        expect(nextSort(null, 'name')).toBe('name');
        expect(nextSort('other', 'name')).toBe('name');
    });

    it('sorts descending when the column is currently ascending', () => {
        expect(nextSort('name', 'name')).toBe('-name');
    });

    it('sorts ascending when the column is currently descending', () => {
        expect(nextSort('-name', 'name')).toBe('name');
    });
});
