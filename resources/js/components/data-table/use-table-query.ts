import { router } from '@inertiajs/react';

export function buildTableUrl(
    baseUrl: string,
    params: Record<string, string>,
    currentSearch: string = typeof window !== 'undefined' ? window.location.search : '',
): string {
    const search = new URLSearchParams(currentSearch);
    for (const [key, value] of Object.entries(params)) {
        if (value === '') search.delete(key);
        else search.set(key, value);
    }
    // Any param change other than paging itself resets to page 1.
    if (!('page' in params)) search.set('page', '1');
    return `${baseUrl}?${search.toString()}`;
}

export function visitTable(baseUrl: string, params: Record<string, string>): void {
    router.get(buildTableUrl(baseUrl, params), {}, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * Given the current `sort` query value and the column being clicked, returns
 * the next sort value to apply: asc -> desc -> asc, starting from asc when
 * the column isn't already the active sort.
 */
export function nextSort(current: string | null, column: string): string {
    if (current === column) return `-${column}`;
    if (current === `-${column}`) return column;
    return column;
}
