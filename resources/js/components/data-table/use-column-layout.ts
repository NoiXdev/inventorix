import { useEffect, useState } from 'react';
import type { ColumnOrderState, ColumnSizingState, VisibilityState } from '@tanstack/react-table';

export interface ColumnLayout {
    order: ColumnOrderState;
    sizing: ColumnSizingState;
    visibility: VisibilityState;
}

const EMPTY: ColumnLayout = { order: [], sizing: {}, visibility: {} };

const storageKey = (key: string) => `data-table:${key}`;

function load(key: string): ColumnLayout {
    try {
        const raw = localStorage.getItem(storageKey(key));
        return raw ? { ...EMPTY, ...JSON.parse(raw) } : EMPTY;
    } catch {
        return EMPTY;
    }
}

/**
 * Per-table column order / sizing / visibility, persisted in localStorage so
 * a user's layout survives Inertia visits and reloads. Storage failures
 * (private mode, quota) silently fall back to the default layout.
 */
export function useColumnLayout(key: string) {
    const [layout, setLayout] = useState<ColumnLayout>(() => load(key));

    useEffect(() => {
        try {
            localStorage.setItem(storageKey(key), JSON.stringify(layout));
        } catch {
            // ignore
        }
    }, [key, layout]);

    return [layout, setLayout, () => setLayout(EMPTY)] as const;
}

/** Moves `from` to the position of `to` within `order`. */
export function moveColumn(order: string[], from: string, to: string): string[] {
    const next = order.filter((id) => id !== from);
    const index = next.indexOf(to);
    if (index === -1) return order;
    // Dropping onto a column further right places the dragged one after it.
    next.splice(order.indexOf(from) < order.indexOf(to) ? index + 1 : index, 0, from);
    return next;
}
